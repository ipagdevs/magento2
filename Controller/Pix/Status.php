<?php

namespace Ipag\Payment\Controller\Pix;

use Ipag\Payment\Factory\HelperFactory;
use Ipag\Payment\Model\Support\ArrUtils;
use Ipag\Payment\Model\Support\PixExpirationUtils;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

/**
 * Confirmação do pagamento Pix para a tela de sucesso.
 *
 * O template consulta esta rota em vez de confiar no evento de websocket: o servidor
 * (`ipag-websocket/app/server.js`) emite uma string, não um objeto com status, então o
 * evento serve como gatilho — quem afirma que o pagamento entrou é o backend.
 *
 * A ação não aceita nenhum identificador de pedido. O pedido vem da sessão de checkout,
 * o que torna impossível por construção perguntar pelo pedido de outra pessoa: aceitar
 * um `order_id` por query string permitiria varrer `000000041`, `000000042`... para
 * descobrir quais pedidos da loja foram pagos.
 *
 * Só devolve booleanos. A resposta do iPag traz nome, CPF, NSU e dados do adquirente,
 * e nada disso pode atravessar para o browser.
 *
 * É deliberadamente read-only: não grava status, não cria invoice, não muda o state do
 * pedido. Reconciliar o pedido é responsabilidade do webhook
 * (`Ipag\Payment\Controller\Notification\Callback`), não de um GET que o cliente dispara.
 */
class Status extends Action implements HttpGetActionInterface
{
    /**
     * Status da API iPag que confirmam um Pix pago: só 8 CAPTURED.
     *
     * `Redirect\Result::execute()` aceita o par `['5', '8']`, mas aquele controller
     * também atende `ipagcc`, onde 5 PRE AUTHORIZED é um estado real do cartão. Pix não
     * pré-autoriza: vai de 2 WAITING PAYMENT direto para 8. Confirmado em teste manual
     * do fluxo completo — o 5 que aparecia em ambiente local vinha da API simulada,
     * forçado à mão para exercitar a troca de status, não do fluxo real.
     */
    private const PAID_STATUSES = [8];

    /**
     * Intervalo mínimo entre duas consultas à API iPag para o mesmo pedido, em segundos.
     */
    private const CONSULT_MIN_INTERVAL = 10;

    private const CACHE_KEY_PREFIX = 'ipag_pix_status_';

    private const PIX_METHOD_CODE = 'ipagpix';

    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var HelperFactory */
    private $helperFactory;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var CacheInterface */
    private $cache;

    /** @var \Ipag\Payment\Logger\Logger */
    private $ipagLogger;

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param JsonFactory $resultJsonFactory
     * @param HelperFactory $helperFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param CacheInterface $cache
     * @param \Ipag\Payment\Logger\Logger $ipagLogger
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        JsonFactory $resultJsonFactory,
        HelperFactory $helperFactory,
        ScopeConfigInterface $scopeConfig,
        CacheInterface $cache,
        \Ipag\Payment\Logger\Logger $ipagLogger
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->helperFactory = $helperFactory;
        $this->scopeConfig = $scopeConfig;
        $this->cache = $cache;
        $this->ipagLogger = $ipagLogger;
    }

    /**
     * Responde se o Pix do pedido em sessão já foi pago e se o QR Code expirou.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();

            if (!$order || !$order->getId() || !$this->isPixOrder($order)) {
                return $this->respond(false, false);
            }

            $expired = $this->isPixExpired($order);

            // O estado local não passa pelo throttle: se o webhook chegou no meio da
            // janela de cache, a confirmação aparece na consulta seguinte.
            if ($this->isPaidLocally($order)) {
                return $this->respond(true, $expired);
            }

            if ($expired) {
                return $this->respond(false, true);
            }

            return $this->respond($this->isPaidAccordingToProvider($order), false);
        } catch (\Throwable $th) {
            $this->ipagLogger->error(
                'pix status: could not resolve payment status',
                ['exception' => $this->describeThrowable($th)]
            );

            return $this->respond(false, false);
        }
    }

    /**
     * Descreve a exceção sem despejar o stack trace no log.
     *
     * `(string) $th` inclui o trace, e o trace inclui os argumentos de cada frame
     * (truncados em 15 caracteres) quando `zend.exception_ignore_args` está desligado
     * — o caso desta imagem PHP. Como a chave de API viaja como argumento até
     * `Helper\V2\Data::prepareSDKClientProvider($environment, $apiId, $apiKey)`, uma
     * exceção lançada com esse frame na pilha gravaria o prefixo da chave em
     * `var/log/ipag/`. Mensagem e origem bastam para diagnosticar.
     *
     * @param \Throwable $th
     * @return string
     */
    private function describeThrowable(\Throwable $th)
    {
        return sprintf(
            '%s: %s at %s:%d',
            get_class($th),
            $th->getMessage(),
            $th->getFile(),
            $th->getLine()
        );
    }

    /**
     * Monta a resposta JSON — só os dois booleanos, nunca dado do pedido.
     *
     * @param bool $paid
     * @param bool $expired
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function respond($paid, $expired)
    {
        return $this->resultJsonFactory->create()->setData([
            'paid' => (bool) $paid,
            'expired' => (bool) $expired,
        ]);
    }

    /**
     * Só pedidos pagos com Pix respondem a esta rota.
     *
     * @param Order $order
     * @return bool
     */
    private function isPixOrder($order)
    {
        $payment = $order->getPayment();

        return $payment !== null && $payment->getMethod() === self::PIX_METHOD_CODE;
    }

    /**
     * Pagamento reconhecido sem sair do Magento.
     *
     * `registerAdditionalInfoTransactionData()` achata a resposta do iPag em chaves
     * pontuadas, então `payment.status` guarda o último status conhecido — inclusive o
     * que o webhook gravou. Quando ele existe, decide sozinho.
     *
     * O state do pedido só entra quando não há status gravado, e não como reforço: o
     * mapa de `Helper\AbstractData` manda 5 PRE AUTHORIZED para `STATE_PROCESSING`, o
     * mesmo state de 8 CAPTURED. Consultar os dois faria o state confirmar a tela
     * justamente no status que `PAID_STATUSES` deixou de aceitar.
     *
     * Como fallback ele continua valendo a pena: o state cobre o pedido em que o módulo
     * nunca gravou status, e não depende do mapeamento configurável em
     * `payment/ipagbase/order_status/approved` — um lojista que aponte "approved" para
     * um status custom fora de `processing` quebraria um check baseado só em state.
     *
     * @param Order $order
     * @return bool
     */
    private function isPaidLocally($order)
    {
        $payment = $order->getPayment();

        $providerStatus = $payment !== null
            ? $payment->getAdditionalInformation('payment.status')
            : null;

        if ($providerStatus !== null && $providerStatus !== '') {
            return $this->isPaidStatus($providerStatus);
        }

        return in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true);
    }

    /**
     * Expiração do QR Code resolvida no servidor.
     *
     * Assim o poll para mesmo quando o relógio da aba divergir.
     *
     * @param Order $order
     * @return bool
     */
    private function isPixExpired($order)
    {
        $payment = $order->getPayment();

        if ($payment === null) {
            return false;
        }

        $expiresAt = PixExpirationUtils::parse($payment->getAdditionalInformation('pix.expiresAt'));

        // Sem expiração conhecida não há como afirmar que expirou; o poll segue.
        if ($expiresAt === null) {
            return false;
        }

        return $expiresAt->getTimestamp() <= time();
    }

    /**
     * Consulta a API iPag, no máximo uma vez a cada `CONSULT_MIN_INTERVAL` por pedido.
     *
     * Sem o throttle, cada aba parada na tela de sucesso viraria um cliente batendo na
     * API em loop. O resultado é cacheado inclusive quando negativo — é justamente a
     * janela em que não se deve perguntar de novo.
     *
     * A consulta que falha também abre a janela, e é o caso que mais importa: o helper
     * **lança** em vez de devolver `['error' => ...]` (a v2 propaga o
     * `HttpClientException` do SDK, inclusive no 404 "Transaction not found"), então
     * deixar a exceção subir para o `catch` do `execute()` pularia o `save()` e cada
     * poll de cada aba consultaria a API de novo — justamente o loop que o throttle
     * existe para impedir.
     *
     * @param Order $order
     * @return bool
     */
    private function isPaidAccordingToProvider($order)
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $order->getIncrementId();

        $cached = $this->cache->load($cacheKey);

        if ($cached !== false) {
            return $cached === '1';
        }

        $paid = false;

        try {
            $paid = $this->consultProvider($order);
        } catch (\Throwable $th) {
            // Falha de consulta é esperada e recuperável: o webhook continua sendo quem
            // reconcilia o pedido. Nível `info` porque a alternativa, com o poll de 15s,
            // é uma linha de log por aba a cada 15 segundos.
            $this->ipagLogger->info('pix status: provider consult failed', [
                'order' => $order->getIncrementId(),
                'exception' => $this->describeThrowable($th),
            ]);
        }

        $this->cache->save($paid ? '1' : '0', $cacheKey, [], self::CONSULT_MIN_INTERVAL);

        return $paid;
    }

    /**
     * Pergunta o status do pedido à API iPag, server-side.
     *
     * @param Order $order
     * @return bool
     */
    private function consultProvider($order)
    {
        $incrementId = $order->getIncrementId();

        $helper = $this->helperFactory->createForVersion($this->getApiVersion((int) $order->getStoreId()));

        $response = $helper->getProviderTransactionByOrderId($incrementId);

        if (!is_array($response) || isset($response['error'])) {
            $this->ipagLogger->info('pix status: provider consult returned no usable data', [
                'order' => $incrementId,
            ]);

            return false;
        }

        $responseOrderId = ArrUtils::get($response, 'order.orderId');

        // Mesma guarda de `Redirect\Result`: não confiar no status sem confirmar de qual
        // pedido ele é.
        if ((string) $responseOrderId !== (string) $incrementId) {
            $this->ipagLogger->error('pix status: provider returned data for another order', [
                'order' => $incrementId,
                'response_order_id' => $responseOrderId,
            ]);

            return false;
        }

        return $this->isPaidStatus(ArrUtils::get($response, 'payment.status'));
    }

    /**
     * Traduz um código de status da API iPag em "pago ou não".
     *
     * @param mixed $status
     * @return bool
     */
    private function isPaidStatus($status)
    {
        if ($status === null || $status === '' || !filter_var($status, FILTER_VALIDATE_INT)) {
            return false;
        }

        return in_array((int) $status, self::PAID_STATUSES, true);
    }

    /**
     * Versão da API configurada para a loja do pedido.
     *
     * @param int|null $storeId
     * @return string
     */
    private function getApiVersion($storeId = null)
    {
        $version = $this->scopeConfig->getValue(
            'payment/ipagbase/apiVersion',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $version ?: 'v1';
    }
}

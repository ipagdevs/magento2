<?php

namespace Ipag\Payment\Controller\Redirect;

use Ipag\Payment\Factory\HelperFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\CsrfAwareActionInterface;

class Result extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var HelperFactory
     */
    protected $helperFactory;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @param Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param OrderFactory $orderFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param HelperFactory $helperFactory
     * @param EncryptorInterface $encryptor
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Context $context,
        \Psr\Log\LoggerInterface $logger,
        OrderFactory $orderFactory,
        ScopeConfigInterface $scopeConfig,
        HelperFactory $helperFactory,
        EncryptorInterface $encryptor,
        CheckoutSession $checkoutSession
    ) {
        $this->_logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->scopeConfig = $scopeConfig;
        $this->helperFactory = $helperFactory;
        $this->encryptor = $encryptor;
        $this->checkoutSession = $checkoutSession;

        parent::__construct($context);
    }

    /**
     * Resolve o desfecho do pagamento pelo token de retorno e devolve o cliente ao checkout.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $token = $this->getRequest()->getParam('p');
        $order = null;

        $this->_logger->debug('redirect result controller called', ['token' => $token]);

        try {

            if (empty($token)) {
                $this->_logger->notice('Missing redirect token, redirecting to home');
                return $this->redirectToHome();
            }

            $decoded = base64_decode($token, true);

            if ($decoded === false) {
                $this->_logger->notice('Invalid base64 token, redirecting to home', ['token' => $token]);
                return $this->redirectToHome();
            }

            $payload = $this->encryptor->decrypt($decoded);

            $data = json_decode($payload, true);

            if (empty($data) || empty($data['order'])) {
                $this->thrownException(
                    'redirect result controller: Invalid token payload',
                    ['token' => $token, 'payload' => $payload]
                );
            }

            $orderId = $data['order'];
            $this->_logger->debug('Decoded redirect token', ['order' => $orderId]);

            $order = $this->orderFactory->create()->loadByIncrementId($orderId);

            if (!$order || !$order->getId()) {
                $this->thrownLocalizedException('Order not found for decoded token', ['order' => $orderId]);
            }

            $ipagHelper = $this->getPaymentHelper((int) $order->getStoreId());

            $response = $ipagHelper->getProviderTransactionByOrderId($orderId);

            if (!is_array($response)) {
                $this->thrownLocalizedException(
                    'Invalid provider response for order consult',
                    ['order' => $orderId]
                );
            }

            if (isset($response['error'])) {
                $this->thrownLocalizedException(
                    'iPag returned an error for order consult',
                    [
                        'order' => $orderId,
                        'error' => $response['error'],
                        'message' => $response['errorMessage'] ?? null,
                    ]
                );
            }

            $responseOrderId = $response['order']['orderId'] ?? null;

            if ($responseOrderId != $orderId) {
                $this->thrownLocalizedException(
                    'iPag returned invalid order data for order consult',
                    ['order' => $orderId, 'response_order_id' => $responseOrderId]
                );
            }

            $paymentStatus = isset($response['payment']['status']) ? (string) $response['payment']['status'] : null;
            $paymentMethod = $order->getPayment() ? $order->getPayment()->getMethod() : null;

            $this->_logger->debug(
                'Resolved redirect payment status',
                ['order' => $orderId, 'payment_status' => $paymentStatus, 'payment_method' => $paymentMethod]
            );

            if ($paymentStatus !== null && in_array($paymentStatus, ['5', '8'])) {
                return $this->redirectToCheckoutSuccess($order);
            }

            if ($paymentMethod === 'ipagcc' && $paymentStatus !== null && in_array($paymentStatus, ['3', '7'])) {
                return $this->redirectToCheckoutFailure($order, $this->getCardDeclinedMessage());
            }

            return $this->redirectToPaymentNotConfirmed($order);

        } catch (LocalizedException $e) {
            $this->messageManager->addError($e->getMessage());
            $this->_logger->error($e->getMessage());
            return $this->redirectToPaymentNotConfirmed($order);
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $this->_logger->critical($e->getMessage());
            return $this->redirectToHome();
        }
    }

    /**
     * Registra o erro no log e interrompe o fluxo.
     *
     * @param string $message
     * @param array $rest
     * @return void
     * @throws \Exception
     */
    private function thrownException($message, ...$rest)
    {
        $this->_logger->error($message, ...$rest);
        throw new \Exception($message);
    }

    /**
     * Registra o erro no log e interrompe o fluxo com uma exceção exibível ao cliente.
     *
     * @param string $message
     * @param array $rest
     * @return void
     * @throws LocalizedException
     */
    private function thrownLocalizedException($message, ...$rest)
    {
        $this->_logger->error($message, ...$rest);
        throw new LocalizedException(__($message));
    }

    /**
     * Allow external POSTs by disabling default CSRF validation for this action.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Allow the request (disable CSRF check).
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Devolve o cliente à tela de sucesso do checkout, a mesma dos demais métodos de pagamento.
     *
     * As três primeiras chaves são exigidas pelo SuccessValidator do Magento — sem qualquer uma
     * delas o cliente é mandado para o carrinho. A quarta é a que o Block\Success usa para carregar
     * o pedido e montar os painéis de Pix e boleto.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function redirectToCheckoutSuccess($order)
    {
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getEntityId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('checkout/onepage/success');

        $this->_logger->debug(
            'Redirecting approved redirect payment to checkout success page',
            ['path' => 'checkout/onepage/success', 'order' => $order->getIncrementId()]
        );

        return $resultRedirect;
    }

    /**
     * Desfecho de pagamento não confirmado que não é cartão negado.
     *
     * @param \Magento\Sales\Model\Order|null $order
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function redirectToPaymentNotConfirmed($order)
    {
        return $this->redirectToCheckoutFailure($order, $this->getPaymentNotConfirmedMessage());
    }

    /**
     * Devolve o cliente à tela de falha do checkout com a mensagem em sessão.
     *
     * Sem pedido carregado não há como usar essa tela: o Onepage\Failure exige last_quote_id e
     * last_order_id e, sem eles, mandaria o cliente ao carrinho em silêncio. Nesse caso cai na home,
     * onde a mensagem registrada no messageManager ainda é exibida.
     *
     * @param \Magento\Sales\Model\Order|null $order
     * @param \Magento\Framework\Phrase|string $message
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function redirectToCheckoutFailure($order, $message)
    {
        if (!$order || !$order->getId()) {
            $this->messageManager->addError($message);
            $this->_logger->debug('No order loaded for the failure page, redirecting to home');

            return $this->redirectToHome();
        }

        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getEntityId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setErrorMessage($message);

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('checkout/onepage/failure');

        $this->_logger->debug(
            'Redirecting unconfirmed redirect payment to checkout failure page',
            ['path' => 'checkout/onepage/failure', 'order' => $order->getIncrementId()]
        );

        return $resultRedirect;
    }

    /**
     * Mensagem exibida quando a operadora nega o cartão.
     *
     * @return \Magento\Framework\Phrase
     */
    private function getCardDeclinedMessage()
    {
        return __('Seu cartão não pode ser processado, entre em contato com sua operadora.');
    }

    /**
     * Mensagem exibida quando o pagamento não foi confirmado e não é cartão negado.
     *
     * Complementa, e não repete, o que a tela de falha já diz: o order/failure/additional.phtml
     * cuida da orientação por método de pagamento e o failure.phtml imprime o número do pedido.
     *
     * @return \Magento\Framework\Phrase
     */
    private function getPaymentNotConfirmedMessage()
    {
        return __('Não foi possível confirmar o pagamento. Verifique com seu banco se a cobrança foi efetivada.');
    }

    /**
     * Resolve o helper da versão de API configurada para a loja.
     *
     * @param int|null $storeId
     * @return \Ipag\Payment\Helper\AbstractData
     */
    private function getPaymentHelper($storeId = null)
    {
        $version = $this->scopeConfig->getValue(
            'payment/ipagbase/apiVersion',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->helperFactory->createForVersion($version ?: 'v1');
    }

    /**
     * Desfecho de último recurso, quando não há pedido para levar a nenhuma tela do checkout.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function redirectToHome()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('');
        return $resultRedirect;
    }
}

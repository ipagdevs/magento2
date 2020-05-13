<?php
namespace Ipag\Payment\Gateway\Client;

//use Eway\EwayRapid\Model\Config;
//use Eway\Rapid\Contract\Client;
#use Eway\Rapid\Model\Response\CreateCustomerResponse;
use Magento\Framework\Exception\PaymentException;
use Psr\Log\LoggerInterface;

class Sdk implements \Magento\Payment\Gateway\Http\ClientInterface
{
    const CREATE_TRANSACTION   = 'create_transaction';
    const QUERY_TRANSACTION    = 'query_transaction';
    const QUERY_CUSTOMER_TOKEN = 'query_customer_token';
    const CREATE_CUSTOMER      = 'create_customer';
    const UPDATE_CUSTOMER      = 'update_customer';
    const CANCEL_TRANSACTION   = 'cancel_transaction';
    const REFUND_TRANSACTION   = 'refund_transaction';

    /** @var LoggerInterface */
    protected $logger; // @codingStandardsIgnoreLine

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $config; // @codingStandardsIgnoreLine

    /** @var string */
    protected $operation; // @codingStandardsIgnoreLine

    /** @var ClientFactory */
    protected $clientFactory; // @codingStandardsIgnoreLine

    public function __construct(
        LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        ClientFactory $clientFactory,
        $operation
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->operation = $operation;
        $this->clientFactory = $clientFactory;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return array
     * @throws PaymentException
     * @codingStandardsIgnoreStart
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $apiKey = $transferObject->getAuthUsername();
        $apiPassword = $transferObject->getAuthPassword();
        $apiEndpoint = $transferObject->getUri();

        $logger = $this->config->getValue('debug') ? $this->logger : null;
        $client = $this->clientFactory->create($apiKey, $apiPassword, $apiEndpoint, $logger);

        if ($logger) {
            $logger->debug(sprintf(
                '>>>>>>>>>>>> REQUEST [%s:%s] BODY=%s',
                $this->operation,
                $transferObject->getMethod(),
                json_encode($transferObject->getBody())
            ));
        }

        switch ($this->operation) {
            case self::CREATE_TRANSACTION:
                $response = $client->createTransaction($transferObject->getMethod(), $transferObject->getBody());
                $this->logResponse($logger, $response);
                break;

            case self::QUERY_TRANSACTION:
                $data = $transferObject->getBody();
                $reference = !empty($data[Config::ACCESS_CODE]) ? $data[Config::ACCESS_CODE] : $data[Config::TRANSACTION_ID];
                $response = $client->queryTransaction($reference);
                $this->logResponse($logger, $response);
                if (empty($response->Transactions)) {
                    throw new PaymentException(__('Unable to place order. Please refresh the page and try again.'));
                }
                $response = $response->Transactions[0];
                break;

            case self::QUERY_CUSTOMER_TOKEN:
                $data = $transferObject->getBody();
                $response = $client->queryCustomer($data[Config::TOKEN_CUSTOMER_ID]);
                $this->logResponse($logger, $response);
                if (empty($response->Customers)) {
                    throw new PaymentException(__('Unable to query customer.'));
                }
                // For compatibility with other validators & handlers
                $response = new CreateCustomerResponse([Config::CUSTOMER => $response->Customers[0]]);
                break;

            case self::CREATE_CUSTOMER:
                $response = $client->createCustomer($transferObject->getMethod(), $transferObject->getBody());
                $this->logResponse($logger, $response);
                break;

            case self::UPDATE_CUSTOMER:
                $response = $client->updateCustomer($transferObject->getMethod(), $transferObject->getBody());
                $this->logResponse($logger, $response);
                break;

            case self::CANCEL_TRANSACTION:
                $data = $transferObject->getBody();
                $response = $client->cancelTransaction($data[Config::TRANSACTION_ID]);
                $this->logResponse($logger, $response);
                break;

            case self::REFUND_TRANSACTION:
                $response = $client->refund($transferObject->getBody());
                $this->logResponse($logger, $response);
                break;

            default:
                throw new \InvalidArgumentException(sprintf('Unknown operation [%s]', $this->operation));
        }

        if ($response instanceof \Eway\Rapid\Model\Response\AbstractResponse) {
            $errors = $response->getErrors();
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    switch ($error) {
                        case Client::ERROR_HTTP_AUTHENTICATION_ERROR:
                            throw new PaymentException(__('Invalid API key or password'));
                        case Client::ERROR_HTTP_SERVER_ERROR:
                            throw new PaymentException(__('Gateway error'));
                        case Client::ERROR_CONNECTION_ERROR:
                            throw new PaymentException(__('Connection error'));
                    }
                }
            }
        }

        $responseArr = $response->toArray();

        return $responseArr;
    }
    // @codingStandardsIgnoreEnd

    protected function logResponse($logger, $response) // @codingStandardsIgnoreLine
    {
        if ($logger) {
            $logger->debug('<<<<<<<<<<<< RESPONSE: BODY=' . json_encode($response->toArray()));
        }
    }
}

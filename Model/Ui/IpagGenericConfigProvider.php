<?php
namespace Ipag\Payment\Model\Ui;

use Magento\Framework\UrlInterface;

class IpagGenericConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    const CODE = 'ipag_abstract';

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $config; // @codingStandardsIgnoreLine

    /** @var UrlInterface */
    protected $urlBuilder; // @codingStandardsIgnoreLine

    /** @var array */
    protected $methodSpecificConfig; // @codingStandardsIgnoreLine

    /** @var ManagerInterface */
    protected $tokenManager; // @codingStandardsIgnoreLine

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        UrlInterface $urlBuilder,
        #ManagerInterface $tokenManager,
        $methodSpecificConfig = []
    ) {
    
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->methodSpecificConfig = $methodSpecificConfig;
        #$this->tokenManager = $tokenManager;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $tokenEnabled = (bool)$this->config->getValue('token_enabled');
        $tokenEnabled = $tokenEnabled && $this->tokenManager->getCurrentCustomer();

        $config = [
            'connectionType'    => $this->config->getValue('connection_type'),
            'token_enabled'     => $tokenEnabled,
            'save_text'         => $this->config->getValue('save_text'),
            'save_card_checked' => (bool)$this->config->getValue('save_card_checked'),
            'can_edit_token'    => (bool)$this->config->getValue('can_edit_token')
        ];

        if ($tokenEnabled) {
            $tokenList = $this->prepareTokenList();
            $selectedTokenId = $this->tokenManager->getDefaultToken();

            if (!$selectedTokenId || !isset($tokenList[$selectedTokenId])) {
                $selectedTokenId =  $this->tokenManager->getLastTokenId();
            }

            if (!$selectedTokenId || !isset($tokenList[$selectedTokenId])) {
                $selectedTokenId = '';
            }

            $config['token_list'] = $tokenList;
            $config['selected_token'] = $selectedTokenId;
        }

        $config = array_merge($config, $this->getAllMethodConfig());

        return [
            'payment' => [
                self::CODE => $config,
            ]
        ];
    }

    protected function prepareTokenList() // @codingStandardsIgnoreLine
    {
        $tokenList = [];
        foreach ($this->tokenManager->getActiveTokenList() as $id => $token) {
            /** @var \Ipag\Payment\Model\Customer\Token $token */
            $tokenList[$id] = [
                'token_id'  => $id,
                'card'      => $token->getCard(),
                'owner'     => $token->getOwner(),
                'exp_month' => (int) $token->getExpMonth(),
                'exp_year'  => $token->getExpYear(),
                'type'      => $token->getType(),
            ];
        }

        return $tokenList;
    }

    public function getAllMethodConfig()
    {
        $config = [];

        foreach ($this->methodSpecificConfig as $methodConfig) {
            if ($methodConfig instanceof MethodSpecificConfigInterface) {
                $connectionType = $methodConfig->getConnectionType();
                $config[$connectionType] = $methodConfig->getMethodConfig();
                $config[$connectionType]['method_renderer'] = $methodConfig->getMethodRendererPath();
                $config[$connectionType]['label'] = $methodConfig->getLabel();
            }
        }

        return $config;
    }

    /**
     * @return \Ipag\Payment\Model\Ui\MethodSpecificConfigInterface
     */
    public function getActiveMethodConfig()
    {
        $connectionType = $this->config->getValue('connection_type');
        return isset($this->methodSpecificConfig[$connectionType]) ?
            $this->methodSpecificConfig[$connectionType] : null;
    }
}

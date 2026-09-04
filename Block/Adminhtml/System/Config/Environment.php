<?php
namespace Ipag\Payment\Block\Adminhtml\System\Config;

class Environment implements \Magento\Framework\Option\ArrayInterface
{

   public function toOptionArray()
    {
        $defaultOptions = [
            'production' => 'Produção',
            'sandbox' => 'Sandbox - Ambiente de Teste',
        ];

        // A presença da URL é o que define existir ambiente local: é ela que os
        // helpers usam como endpoint quando `environment_mode=local`
        // (`Helper\Data::AuthorizationValidate()` e
        // `Helper\V2\Data::prepareSDKEnvironment()`). Oferecer a opção sem ela
        // deixaria o lojista escolher um ambiente que não responde — em v2 o SDK
        // recusa a URL vazia com "The environment must be valid" e a transação
        // morre. Uma variável só, então, em vez de um booleano que pode
        // discordar do endpoint.
        //
        // O `trim` importa: `getenv()` devolve `false` quando a variável não
        // existe, mas devolve a string crua quando existe — e um valor só com
        // espaço em branco é truthy, o que reintroduziria a opção sem endpoint
        // utilizável. O cast cobre o `false`.
        $localEndpoint = trim((string) getenv('IPAG_ENVIRONMENT_URL'));

        if ($localEndpoint !== '') {
            $defaultOptions['local'] = 'Local - Ambiente de Desenvolvimento';
        }

        return $defaultOptions;
    }
}

# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto adere ao [Versionamento Semântico](https://semver.org/spec/v2.0.0.html).

## v2.1.0 - 2026-09-04
- Unifica o desfecho do fluxo de redirecionamento com as telas padrão do checkout: o pagamento aprovado passa a terminar em `checkout/onepage/success` (com número do pedido e os painéis de QR Code do Pix e linha digitável do boleto, como nos demais métodos) e o não confirmado em `checkout/onepage/failure`, em vez de duas páginas próprias do módulo que não mostravam o pedido nem seguiam o tema da loja.
- Remove o fluxo paralelo que deixou de ser usado: os templates `order/redirect/success.phtml` e `order/redirect/error.phtml`, os layouts `ipag_redirect_success.xml` e `ipag_redirect_error.xml` e a injeção de `PageFactory` no controller.
- Remove o código morto do `Controller/Redirect/Result`, sobra do tempo em que ele criava invoice sozinho: dez dependências injetadas e nunca lidas, um método sem chamadas, um import não usado e um comentário de compatibilidade que descrevia um bloco já removido. O construtor sai de 18 para 7 dependências — quem estende o controller por fora precisa ajustar a assinatura.
- O fluxo de redirecionamento passa a disparar o evento `checkout_onepage_controller_success_action`, que a página própria não disparava: extensões de analytics passam a trackear essas compras.
- Corrige a tela de falha do checkout, que sempre caía no texto genérico de fallback: o Magento não marca o bloco `checkout.failure` como não-cacheável (só o `checkout.success`), então com o cache de página cheia ligado a sessão de checkout era limpa antes do render e a tela perdia o número do pedido e a mensagem de erro. Afetava também o pagamento com cartão negado.

## v2.0.8 - 2026-09-04
- Corrige a confirmação do pagamento Pix na tela de sucesso, que nunca funcionou: o evento de websocket passa a ser apenas um gatilho e a confirmação vem de uma consulta ao backend (`/ipag/pix/status`), com poll de segurança para quando o websocket estiver indisponível.
- Adiciona `wss://websocket.ipag.com.br` e `https://websocket.ipag.com.br` ao `connect-src` do CSP, necessários para o cliente socket.io conectar em lojas com CSP em modo restrict.

## v2.0.7 - 2026-09-02
- Exibe o tempo de expiração do QR Code Pix (horário e contador regressivo) na tela de sucesso do checkout, e o horário de expiração no painel Payment Information (admin e frontend).
- Torna configurável o tempo de expiração enviado no pagamento Pix (`payment/ipagpix/expires_in`), mantendo o valor atual (60 minutos) como default.
- Exibe o código do QR Code Pix na tela de sucesso em caixa copiável com feedback visual, no mesmo padrão da linha digitável do boleto.
- Corrige as caixas do Pix e do boleto estourando a largura da tela no mobile (`box-sizing`).

## v2.0.6 - 2026-09-02
- Repassa a mensagem de erro de validação (4xx) da API iPag para o cliente no checkout (boleto, pix e cartão v2), em vez da mensagem genérica "Payment service unavailable".

## v2.0.5 - 2026-09-02
- Exibe a linha digitável do boleto na tela de sucesso do checkout e no painel Payment Information (admin e "Meus Pedidos"), e corrige mistura de idiomas nesse painel.

## v2.0.4 - 2026-07-30
- Trunca os campos de produto que excedem os limites de caracteres da API do iPag (SKU, nome e descrição), evitando que a transação seja rejeitada.

## v2.0.3 - 2026-05-27
- Adiciona novos itens de tradução PT-BR no módulo.

## v2.0.2 - 2026-04-22
- Adiciona novo fluxo do método de pagamento cartão.

## v2.0.1 - 2026-04-16
- Adiciona suporte para as novas versões do pacote: endroid/qr-code.

## v2.0.0 - 2026-01-13
- Adiciona suporte do módulo para o iPag SDK V2.

## v1.7.0 - 2025-12-15
- Adiciona novo fluxo de redirecionamento de autenticação de pgamento.

## v1.6.0 - 2025-06-12
- Habilita métodos de pagamento Pix e Boleto nas opções de pagamento de uma nova ordem no painel Admin.

## v1.5.3 - 2025-06-11
- Altera label de opções de parcelas com juros.

## v1.5.2 - 2024-10-23
- Retirada dependências antigas do módulo não utilizadas.

## v1.5.1 - 2024-10-16

- Corrige CSP whitelist do módulo.

## v1.5.0 - 2024-10-02

- Adiciona confirmação de pagamento Pix.

## v1.4.3 - 2024-09-17

- Aprimora extração do atributo NSU dos dados para exibição no detalhes da ordem.

## v1.4.2 - 2024-09-16

- Remove dependencia desnecessária do módulo.

## v1.4.1 - 2024-03-22

### Fixed

- Corrigido o erro do módulo lançando Exception indevidamente no pós processamento de pagamento.

## v1.4.0 - 2024-03-19

### Added

- Adicionados atributos NSU e Auth ID as informações de pagamento no detalhes da ordem.

### Changed

- Melhorada a exibição do atributo Transaction Message no detalhes da ordem.

## v1.3.0 - 2024-03-18

### Added

- Adicionado nova opção (Pre authorized) nas configurações de mapeamento de status do módulo.

## v1.2.0 - 2024-03-05

### Changed

- Adicionado nova seção nas configurações do módulo para mapear o retorno de pagamento com o status da ordem.

## v1.1.1 - 2024-03-05

### Changed

- Corrigido erros no código para suporte do php 8.2.

## v1.1.0 - 2024-02-21

### Changed

- Melhorias no código para suporte ao php 8.2.

## v1.0.39 - 2024-02-21

### Changed

- Melhorada o comportamento do state da order no processamento de pagamento.
# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto adere ao [Versionamento Semântico](https://semver.org/spec/v2.0.0.html).

## v1.5.3 - 2025-06-11
- Altera label de opções de parcelas com juros

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

- Corrigido erros no código para suporte do php 8.2

## v1.1.0 - 2024-02-21

### Changed

- Melhorias no código para suporte ao php 8.2

## v1.0.39 - 2024-02-21

### Changed

- Melhorada o comportamento do state da order no processamento de pagamento
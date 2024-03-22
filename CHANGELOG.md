# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto adere ao [Versionamento Semântico](https://semver.org/spec/v2.0.0.html).

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
# MVP – Organização de entregas gratuitas (Central do Piso)

Projeto de Extensão I (ADS/UCSAL). PHP 8 + MySQL + HTML/CSS/JS, sem frameworks.

## Reuso em outra loja
O nome do sistema e da loja ficam em `inc/config.php` (`NOME_SISTEMA`, `NOME_LOJA`). Para outra loja do mesmo ramo, basta trocar esses valores no `config.local.php`, criar um banco novo e publicar em outro domínio. O fluxo (pedido, separação, rota, conferência) não depende de loja específica.

## O que faz (fluxo ponta a ponta)
1. **Atendimento (`balcao`)** cadastra o pedido uma única vez: cliente, endereço, referência, telefone, data, vendedor e itens (US01).
2. **Depósito (`deposito`)** vê a lista de separação do dia e marca o que foi separado; pode imprimir (US02).
3. **Rotas (`rota`)** escolhe os pedidos, define a ordem e gera a rota do dia, com impressão (US03).
4. Na entrega, confere os itens com o cliente; o que faltar vira **pendência** até ser resolvida (US04).

O sistema não guarda valores, pagamento nem documentos fiscais, e não decide a gratuidade do carreto (negociada pelo vendedor).

## Instalação (Hostinger)
1. hPanel → Bancos de dados → crie um banco MySQL e um usuário.
2. phpMyAdmin → importe `sql/schema.sql`.
3. Copie `inc/config.php` para `inc/config.local.php` e preencha `DB_DSN`, `DB_USER`, `DB_PASS` (o arquivo `config.local.php` não vai para o Git).
4. Publique a pasta `public/` como raiz do site (ou do subdomínio) e a pasta `inc/` **fora** da raiz pública, ajustando o `require` se necessário. Ative HTTPS.
5. Crie os usuários: gere o hash da senha (veja `sql/usuarios_exemplo.sql`) e importe, ou rode `php criar_usuarios.php login "Nome" perfil senha` se houver SSH.

## Segurança e LGPD
Login por perfil, senhas com `password_hash`, consultas com parâmetros (PDO), saída escapada, token CSRF nos formulários e sessão com cookie `HttpOnly`. Em testes e demonstrações use somente dados fictícios.

## Testes
`tests/e2e.sh` executa o fluxo completo com `curl` sobre um banco SQLite local (`tests/schema_sqlite.sql`), apenas para desenvolvimento.

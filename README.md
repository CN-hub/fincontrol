# FinControl

Sistema simples de controle financeiro para gestão de contas parceladas, acompanhamento de vencimentos e exportação de relatórios.

## Funcionalidades

- Cadastro e login de usuários
- Gestão de contas parceladas
- Registro de pagamentos por parcela
- Calendário com feriados e datas comemorativas
- Exportação em CSV, JSON e Excel
- Backup e restauração de dados
- Relatório financeiro em PDF/HTML

## Requisitos

- XAMPP com Apache, MySQL e PHP
- Navegador moderno
- Acesso local na máquina

## Instalação e uso

### 1) Preparar o ambiente

1. Abra o XAMPP.
2. Inicie os serviços de Apache e MySQL.
3. Copie a pasta do projeto para:

   c:\xampp\htdocs\fincontrol

### 2) Criar/importar o banco de dados

Há duas opções:

#### Opção A: importar via phpMyAdmin

1. Acesse http://localhost/phpmyadmin
2. Crie o banco `fincontrol` (se ainda não existir)
3. Importe o arquivo `banco_financeiro.sql`

#### Opção B: importar via terminal MySQL

```bash
mysql -u root -p < banco_financeiro.sql
```

> O arquivo SQL já contém o comando para criar o banco `fincontrol` e as tabelas necessárias.

### 3) Acessar o sistema

Abra no navegador:

```text
http://localhost/fincontrol
```

### 4) Login inicial

Ao abrir o sistema pela primeira vez, se não existir usuário cadastrado, ele cria automaticamente um usuário de desenvolvimento:

- Usuário: `Dev`
- Senha: `123456`

Você pode alterar essa senha depois no cadastro/configuração do sistema.

## Fluxo básico de uso

### Login

- Informe o usuário e senha no formulário de login.
- Caso não tenha conta, clique em cadastrar.

### Cadastro de conta parcelada

- Preencha a descrição, valor total e número de parcelas.
- O sistema calcula automaticamente o valor de cada parcela.
- O próximo vencimento será definido com base na data atual.

### Baixa de parcela

- Clique na opção de baixa para registrar o pagamento de uma parcela.
- O status da conta será atualizado automaticamente.

### Relatórios

- Exporte os dados em CSV, JSON, Excel ou PDF.
- Isso ajuda em conferência e manutenção financeira.

### Backup

- Use a função de exportação de backup para salvar os dados em arquivo JSON.
- Para restaurar, importe o arquivo gerado pelo sistema.

## Estrutura do projeto

```text
fincontrol/
├── index.php
├── banco_financeiro.sql
├── backups/
│   └── ... arquivos de backup JSON
├── README.md
└── .git/
```

## Observações importantes

- O projeto usa o banco MySQL com usuário `root` e senha vazia.
- Se aparecer erro de conexão, verifique se o MySQL do XAMPP está rodando.
- Confirme se o banco `fincontrol` existe e foi importado corretamente.

## Licença

Este projeto foi desenvolvido para uso local e acadêmico/financeiro pessoal.

# Contexto do Projeto

## Projeto
- **Nome:** `trello-burndown-burnup`
- **Entrypoint:** `index.php`

## Stack
- **Backend:** PHP 7.4
- **Frontend:** Bootstrap 5, Chart.js 4

## Fontes de dados
- **Arquivo padrão:** `dados.json`
- **Diretório de uploads:** `uploads/`
- **Configuração de arquivo ativo:** `app_config.json`

## Schema do `app_config.json`
- `latest_uploaded_file`: string
- `updated_at`: datetime ISO-8601

## Regras de negócio
- **Contagem de demanda**
  - Card sem checklist = 1 demanda
  - Card com checklist = quantidade de itens de checklist
- **Gráficos**
  - Burnup (barras)
  - Burndown (barras)

## Funcionalidades implementadas
- Upload de JSON
- Manter uploads anteriores
- Nome de upload com timestamp
- Seleção de arquivo já existente
- Filtro por intervalo de datas

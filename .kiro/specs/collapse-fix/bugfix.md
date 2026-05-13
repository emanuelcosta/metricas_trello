# Bugfix Requirements Document

## Introduction

O collapse das linhas da tabela de cards em andamento não funciona ao clicar nas linhas expansíveis. O projeto carrega o Bootstrap CSS corretamente, mas o Bootstrap JS (necessário para o componente `collapse`) não está incluído. Sem o JS, os atributos `data-bs-toggle="collapse"` e `data-bs-target` não têm efeito, deixando os checklists dos cards inacessíveis. A correção consiste em baixar o Bootstrap JS localmente e incluí-lo no projeto, sem depender de CDN.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN o usuário clica em uma linha da tabela de cards em andamento que possui checklists THEN o sistema não expande nem colapsa o conteúdo, pois o Bootstrap JS não está carregado
1.2 WHEN a página é carregada THEN o sistema não possui nenhum arquivo `bootstrap.bundle.min.js` (ou equivalente) referenciado no HTML, tornando todos os componentes JS do Bootstrap inoperantes

### Expected Behavior (Correct)

2.1 WHEN o usuário clica em uma linha da tabela que possui checklists THEN o sistema SHALL expandir ou colapsar o painel de checklists correspondente usando o mecanismo nativo do Bootstrap collapse
2.2 WHEN a página é carregada THEN o sistema SHALL carregar o Bootstrap JS a partir de um arquivo local em `assets/bootstrap/bootstrap.bundle.min.js`, sem depender de CDN

### Unchanged Behavior (Regression Prevention)

3.1 WHEN o Bootstrap CSS já está carregado via `assets/bootstrap/bootstrap.min.css` THEN o sistema SHALL CONTINUE TO aplicar todos os estilos visuais do Bootstrap normalmente
3.2 WHEN linhas da tabela sem checklists são exibidas THEN o sistema SHALL CONTINUE TO renderizá-las sem botão de expansão e sem comportamento de collapse
3.3 WHEN o Chart.js é carregado via `assets/chartjs/chart.umd.min.js` THEN o sistema SHALL CONTINUE TO renderizar os gráficos de burnup e burndown corretamente

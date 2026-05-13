# collapse-fix Tasks

## Tasks

- [x] 1 Baixar bootstrap.bundle.min.js localmente
  - [x] 1.1 Baixar o arquivo `bootstrap.bundle.min.js` compatível com o CSS presente (verificar versão em `assets/bootstrap/bootstrap.min.css`) e salvar em `assets/bootstrap/bootstrap.bundle.min.js`

- [x] 2 Incluir Bootstrap JS no index.php
  - [x] 2.1 Adicionar `<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>` no `index.php` antes da tag `<script src="assets/chartjs/chart.umd.min.js"></script>`

- [x] 3 Verificar correção
  - [x] 3.1 Confirmar que `assets/bootstrap/bootstrap.bundle.min.js` existe no sistema de arquivos
  - [x] 3.2 Confirmar que `index.php` contém a tag script do Bootstrap JS antes do Chart.js
  - [x] 3.3 Verificar no navegador que clicar em uma linha com checklists expande/colapsa o painel corretamente
  - [x] 3.4 Verificar que os gráficos Chart.js continuam funcionando (preservation check)

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Upload de JSON atualizado</h2>
        <?php if ($uploadSuccess): ?>
            <div class="alert alert-success py-2">Upload concluído com sucesso.</div>
        <?php endif; ?>
        <?php if ($uploadError !== null): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($uploadError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
            <div class="col-md-9">
                <label for="trello_json" class="form-label">Arquivo JSON do Trello</label>
                <input class="form-control" type="file" id="trello_json" name="trello_json" accept=".json" required>
            </div>
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="col-md-3 d-grid">
                <button class="btn btn-primary" type="submit">Enviar arquivo</button>
            </div>
        </form>
        <div class="mt-2 text-muted small">
            Arquivo atual em uso: <strong><?php echo htmlspecialchars($currentFileName, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Arquivos JSON disponíveis</h2>
        <?php if ($deleteSuccess): ?>
            <div class="alert alert-success py-2">Arquivo excluído com sucesso.</div>
        <?php endif; ?>
        <?php if ($deleteError !== null): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($deleteError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($sourceError !== null): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($sourceError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <div class="list-group">
            <?php foreach ($availableFiles as $fileMeta):
                $isActive   = $fileMeta['path'] === $selectedFileRel;
                $useParams  = ['source_file' => $fileMeta['path']];
                if ($filterStartInput !== '') { $useParams['start_date'] = $filterStartInput; }
                if ($filterEndInput !== '')   { $useParams['end_date']   = $filterEndInput; }
                $useHref = strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($useParams);
            ?>
            <div class="list-group-item d-flex align-items-center gap-2 py-2<?php echo $isActive ? ' list-group-item-primary' : ''; ?>">
                <span class="flex-grow-1 small text-truncate" title="<?php echo htmlspecialchars($fileMeta['label'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($isActive): ?><strong><?php endif; ?>
                    <?php echo htmlspecialchars($fileMeta['label'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($isActive): ?></strong><?php endif; ?>
                </span>
                <?php if (!$isActive): ?>
                <a href="<?php echo htmlspecialchars($useHref, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary flex-shrink-0">Usar</a>
                <?php endif; ?>
                <?php if ($fileMeta['short_url'] !== ''): ?>
                <a href="<?php echo $fileMeta['short_url'] . '.json'; ?>" class="btn btn-sm btn-outline-success flex-shrink-0" title="Baixar JSON atualizado do Trello" download="file.json">
                    <i class="fa-solid fa-download"></i>
                </a>
                <?php endif; ?>
                <?php if ($fileMeta['path'] !== 'dados.json'): ?>
                <form method="post" class="flex-shrink-0" onsubmit="return confirm('Excluir o arquivo «<?php echo htmlspecialchars(basename($fileMeta['path']), ENT_QUOTES, 'UTF-8'); ?>»? Esta ação não pode ser desfeita.');">
                    <input type="hidden" name="action" value="delete_file">
                    <input type="hidden" name="file_name" value="<?php echo htmlspecialchars(basename($fileMeta['path']), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="source_file" value="<?php echo htmlspecialchars($selectedFileRel, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir arquivo">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

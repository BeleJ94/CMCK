<section class="field-hero">
    <span class="field-hero-icon"><i class="bi <?= e($icon) ?>"></i></span>
    <div>
        <p class="section-label">Accueil terrain</p>
        <h2><?= e($title) ?></h2>
        <p><?= e($subtitle) ?></p>
    </div>
</section>

<section class="field-action-grid">
    <?php foreach ($actions as $action): ?>
        <a class="field-action-card tone-<?= e($action['tone']) ?>" href="<?= e(base_url($action['path'])) ?>">
            <span class="field-action-icon"><i class="bi <?= e($action['icon']) ?>"></i></span>
            <strong><?= e($action['label']) ?></strong>
            <span><?= e($action['hint']) ?></span>
            <em><i class="bi bi-arrow-right"></i></em>
        </a>
    <?php endforeach; ?>
</section>

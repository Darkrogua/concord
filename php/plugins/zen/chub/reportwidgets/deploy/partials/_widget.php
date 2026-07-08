<div class="report-widget widget-deploy">
    <h3><?= e($this->property('title')) ?></h3>

    <?php if (!isset($error)): ?>
        <div class="deploy-container">
            <button
                type="button"
                class="btn btn-primary oc-icon-refresh deploy-btn"
                data-request="onDeploy"
                data-request-url="<?= e($deployUrl) ?>"
                data-request-flash
                data-stripe-load-indicator
                >
                Деплой
            </button>
        </div>
    <?php else: ?>
        <div class="callout callout-warning">
            <div class="content"><?= e($error) ?></div>
        </div>
    <?php endif ?>
</div>

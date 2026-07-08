<meta name="flow-id" content="<?= e($this->formGetModel()->id ?? '') ?>">
<div id="chub-flow-app"></div>
<?php
echo \Zen\Chub\Classes\Support\Vite::tags([
    'flow_app/js/flow_app.js'
]);
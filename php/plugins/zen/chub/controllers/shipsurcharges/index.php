<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('zen/chub/entities') ?>">База данных</a></li>
        <li><a href="<?= Backend::url('zen/chub/ships') ?>">Теплоходы</a></li>
        <li>Дополнительно оплачивается</li>
    </ul>
<?php Block::endPut() ?>
<?= $this->listRender() ?>

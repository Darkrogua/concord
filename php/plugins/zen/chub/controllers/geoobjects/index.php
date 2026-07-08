<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('zen/chub/entities') ?>">База данных</a></li>
        <li>Географические объекты</li>
    </ul>
<?php Block::endPut() ?>
<?= $this->listRender() ?>
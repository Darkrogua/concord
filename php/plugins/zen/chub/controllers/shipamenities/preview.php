<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('zen/chub/entities') ?>">База данных</a></li>
        <li><a href="<?= Backend::url('zen/chub/ships') ?>">Теплоходы</a></li>
        <li><a href="<?= Backend::url('zen/chub/shipamenities') ?>">На борту теплохода</a></li>
        <li>Просмотр пункта "На борту теплохода"</li>
    </ul>
<?php Block::endPut() ?>

<?= $this->formRender() ?>

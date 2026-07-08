# Задача 0

Существует сущность Command - Состоящая из:
- php/plugins/zen/chub/updates/builder_table_create_zen_chub_commands.php
- php/plugins/zen/chub/models/Command.php
-- php/plugins/zen/chub/models/command/columns.yaml
-- php/plugins/zen/chub/models/command/fields.yaml
- php/plugins/zen/chub/controllers/Commands.php

Теперь её нужно доработать и возможно изменить, чтобы это работало так:
- В поле data можно писать код на php
- В настройках формы в самом верху жолжен быть partial с кнопкой запуска
- Когда нажимаешь кнопку код написаный в поле data выполняется
- Код который храниться в поле data, должен храниться в php-файле, по адресу plugins/zen/chub/data/commands/command_{id}.php но выполняться в методе контроллера
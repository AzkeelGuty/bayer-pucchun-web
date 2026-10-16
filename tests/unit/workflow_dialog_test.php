<?php
declare(strict_types=1);

function has_role(string ...$roles): bool { return true; }
function url(string $path): string { return $path; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function csrf_field(): string { return '<input type="hidden" name="_token" value="test">'; }

require dirname(__DIR__,2).'/app/Views/components/workflow_control.php';

ob_start();
workflow_control('guias',[
    'id'=>7,
    'version'=>3,
    'estado_registro'=>'VALIDADO',
],'/guias');
$html=(string)ob_get_clean();

$checks=[
    'dialog element' => str_contains($html,'<dialog class="workflow-dialog"'),
    'observe opener' => str_contains($html,'data-workflow-dialog-open="workflow-observe-guias-7"'),
    'cancel button' => str_contains($html,'data-workflow-dialog-close'),
    'reason textarea' => str_contains($html,'data-workflow-dialog-reason'),
    'confirm action' => str_contains($html,'Enviar observación'),
    'no legacy details' => !str_contains($html,'<details class="workflow-observe"'),
];

foreach($checks as $label=>$ok){
    if(!$ok) throw new RuntimeException('Workflow dialog failed: '.$label);
}

echo "Workflow observation dialog: OK\n";

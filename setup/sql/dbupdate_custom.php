<#1>
<?php
require_once './Services/Migration/DBUpdate_3560/classes/class.ilDBUpdateNewObjectType.php';
ilDBUpdateNewObjectType::addAdminNode('mme', 'Main Menu');

$ilCtrlStructureReader->getStructure();
?>
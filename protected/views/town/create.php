<?php
/* @var $this TownController */
/* @var $model Town */

$this->breadcrumbs=array(
	'Towns'=>array('index'),
	'Create',
);

$this->menu=array(
	array('label'=>'List Town', 'url'=>array('index')),
	array('label'=>'Manage Town', 'url'=>array('admin')),
);
?>

<h1>Добавить город</h1>

<?php $this->renderPartial('_form', array('model'=>$model)); ?>
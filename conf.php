<?php
$EXT_CONF['audd'] = array(
	'title' => 'Retrieve mp3 info from Audd',
	'description' => 'Extracts 10 seconds of an mp3 song, sends it to Audd.io and evaluates the result.',
	'disable' => false,
	'version' => '1.0.0',
	'releasedate' => '2024-01-02',
	'author' => array('name'=>'Uwe Steinmann', 'email'=>'uwe@steinmann.cx', 'company'=>'MMK GmbH'),
	'config' => array(
		'apitoken' => array(
			'title'=>'API token',
			'help'=>'API token used to access Audd.io. If not set, the number of accesses is vastly reduced.',
			'type'=>'input',
		),
		'startsec' => array(
			'title'=>'Start second',
			'help'=>'Start in seconds of portion of mp3 file sind to Audd.io',
			'type'=>'number',
		),
		'numsecs' => array(
			'title'=>'Duration',
			'help'=>'Number of seconds extracted from mp3 file and send to Audd.io',
			'type'=>'number',
		),
	),
	'constraints' => array(
		'depends' => array('php' => '5.6.40-', 'seeddms' => ['5.1.24-5.1.99', '6.0.17-6.0.99']),
	),
	'icon' => 'icon.svg',
	'changelog' => 'changelog.md',
	'class' => array(
		'file' => 'class.audd.php',
		'name' => 'SeedDMS_ExtAudd'
	),
	'language' => array(
		'file' => 'lang.php',
	),
);
?>

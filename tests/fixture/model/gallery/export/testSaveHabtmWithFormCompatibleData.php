<?php
return array(
	'id' => '1',
	'gallery_id' => NULL,
	'image' => 'someimage.png',
	'title' => 'Image1 Title',
	'created' => NULL,
	'modified' => NULL,
	'images_tags' => array(
		1 => array(
			'id' => '1',
			'image_id' => '1',
			'tag_id' => '1',
		),
		2 => array(
			'id' => '2',
			'image_id' => '1',
			'tag_id' => '3',
		),
		3 => array(
			'id' => '3',
			'image_id' => '1',
			'tag_id' => '6',
		),
	),
	'tags' => array(
		1 => array(
			'id' => '1',
			'name' => 'High Tech'
		),
		3 => array(
			'id' => '3',
			'name' => 'Computer'
		),
		6 => array(
			'id' => '6',
			'name' => 'City'
		),
	),
);
?>
<?php
// Test fixture: a page fieldset with one select field, so the fieldset meta
// path is tested alongside the type path. Used by core-select-meta.php and,
// through fixture-fieldset.php, by the fresh-install test.
function scout_core_test_register_fieldset() {
	scout_core_register_fields(
		'scout_test_fieldset',
		array(
			'label'    => 'Test fieldset',
			'for'      => array( 'post_type' => 'page' ),
			'sections' => array(
				'main' => array(
					'fields' => array(
						'test_layout' => array(
							'control' => 'select',
							'options' => array(
								'wide'   => 'Wide',
								'narrow' => 'Narrow',
							),
						),
					),
				),
			),
		)
	);
}

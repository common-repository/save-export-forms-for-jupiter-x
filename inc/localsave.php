<?php


/* This started life as a copy of /wp-content/plugins/jupiterx-core/includes/extensions/raven/includes/modules/forms/actions/webhook.php */

/* Note this registers itself at the bottom - there are no tidy hooks to use for this, but happily there is a register_custom_action() method we can use */

namespace JupiterX_Core\Raven\Modules\Forms\Actions;

use Elementor\Controls_Manager;

defined('ABSPATH') || die();

/**
 * Localsave Action.
 *
 * Initializing the Localsave action by extending action base.
 *
 * @since 1.3.0
 */
class LocalSave extends Action_Base
{

	/**
	 * Get name.
	 *
	 * @since 1.19.0
	 * @access public
	 */
	public function get_name()
	{
		return 'localsave';
	}

	/**
	 * Get title.
	 *
	 * @since 1.19.0
	 * @access public
	 */
	public function get_title()
	{
		return __('Local save', 'saveformsjx');
	}

	/**
	 * Is private.
	 *
	 * @since 2.0.0
	 * @access public
	 */
	public function is_private()
	{
		return false;
	}

	/**
	 * Exclude form fields in the localsave.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private static $exclude_fields = ['recaptcha', 'recaptcha_v3'];

	/**
	 * Update controls.
	 *
	 * Localsave setting section.
	 *
	 * @since 1.3.0
	 * @access public
	 *
	 * @param object $widget Widget instance.
	 */
	public function update_controls($widget) {}

	/**
	 * Run action.
	 *
	 * Send form data to localsave URL.
	 *
	 * @since 1.3.0
	 * @access public
	 * @static
	 *
	 * @param object $ajax_handler Ajax handler instance.
	 *
	 * @return void
	 */
	public static function run($ajax_handler)
	{
		$settings = $ajax_handler->form['settings'];

		$body = self::get_form_data($ajax_handler, $settings);

		$requestUrl = isset($_SERVER["HTTP_REFERER"]) ? sanitize_url(wp_unslash($_SERVER["HTTP_REFERER"])) : "";

		//If last character is "/" delete
		if (substr($requestUrl, -1) == "/") {
			$requestUrl = substr($requestUrl, 0, -1);
		}

		$slug = explode("/", $requestUrl);
		$slug = $slug[count($slug) - 1];

		$page = get_page_by_path($slug);

		if (isset($page->post_title)) {
			$settings['form_name'] = $page->post_title . " - " . $settings['form_name'];
		}

		$args = array(
			'name'        => $settings['form_name'],
			'post_type'   => 'jx_form_submission',
			'numberposts' => 1
		);

		$post = get_posts($args);

		if (!empty($post)) {
			$original_content = $post[0]->post_content;
			$original_content = json_decode(base64_decode($original_content, true));
			$original_content[] = $body;

			$content = base64_encode(wp_json_encode($original_content));

			$my_post = array(
				'ID'           => $post[0]->ID,
				'post_content' => $content,
			);

			wp_update_post($my_post);
		} else {
			$content = (base64_encode(wp_json_encode([$body])));

			$sub = [
				'post_title' => $settings['form_name'],
				'post_type' => 'jx_form_submission',
				'post_status' => 'publish',
				'post_content' => $content,
			];

			$result = wp_insert_post($sub, true);
		}
	}

	/**
	 * Get form fields data.
	 *
	 * @since 1.3.0
	 * @access private
	 * @static
	 *
	 * @param object $ajax_handler Ajax handler instance.
	 * @param array  $settings Form settings.
	 *
	 * @return array
	 */
	private static function get_form_data($ajax_handler, $settings)
	{
		$fields =  ["Date" => gmdate('d/m/Y H:ia')];

		foreach ($settings['fields'] as $field) {
			if (\in_array($field['type'], self::$exclude_fields, true)) {
				continue;
			}

			$field_value = $ajax_handler->record['fields'][$field['_id']];

			if ('acceptance' === $field['type']) {
				$field_value = empty($field_value) ? __('No', 'saveformsjx') : __('Yes', 'saveformsjx');
			}

			if (empty($field['label']) && empty($field['placeholder'])) {
				$fields[$field['_id']] = $field_value;
			} else if (!empty($field['label'])) {
				$fields[$field['label']] = $field_value;
			} else if (!empty($field['placeholder'])) {
				$fields[$field['placeholder']] = $field_value;
			}
		}

		return $fields;
	}
}

namespace JupiterX_Core\Raven\Modules\Forms;


Module::register_custom_action(new Actions\LocalSave());

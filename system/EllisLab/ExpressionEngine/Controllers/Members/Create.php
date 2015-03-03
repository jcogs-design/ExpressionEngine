<?php

namespace EllisLab\ExpressionEngine\Controllers\Members;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use CP_Controller;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2014, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP Member Create Class
 *
 * @package		ExpressionEngine
 * @subpackage	Control Panel
 * @category	Control Panel
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Create extends Members {

	private $base_url = 'members/create';

	/**
	 * Create Member Form
	 */
	public function index()
	{
		$this->base_url = cp_url($this->base_url);
		$groups = ee()->api->get('MemberGroup')->order('group_title', 'asc')->all();
		$choices = array();

		foreach ($groups as $group)
		{
			$choices[$group->group_id] = $group->group_title;
		}

		$vars['sections'] = array(
			array(
				array(
					'title' => 'member_group',
					'desc' => 'member_group_desc',
					'fields' => array(
						'group_id' => array(
							'type' => 'dropdown',
							'choices' => $choices,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'username',
					'desc' => 'username_desc',
					'fields' => array(
						'username' => array('type' => 'text', 'required' => TRUE)
					)
				),
				array(
					'title' => 'users_email',
					'desc' => 'users_email_desc',
					'fields' => array(
						'email' => array('type' => 'text', 'required' => TRUE)
					)
				),
				array(
					'title' => 'password',
					'desc' => 'password_desc',
					'fields' => array(
						'password' => array('type' => 'password', 'required' => TRUE)
					)
				),
				array(
					'title' => 'confirm_password',
					'desc' => 'confirm_password_desc',
					'fields' => array(
						'password' => array('type' => 'password', 'required' => TRUE)
					)
				)
			)
		);

		ee()->form_validation->set_rules(array(
			array(
				 'field'   => 'group_id',
				 'label'   => 'lang:member_group',
				 'rules'   => 'required'
			),
			array(
				 'field'   => 'username',
				 'label'   => 'lang:username',
				 'rules'   => 'required|valid_username'
			),
			array(
				 'field'   => 'email',
				 'label'   => 'lang:email',
				 'rules'   => 'required|valid_email'
			),
			array(
				 'field'   => 'password',
				 'label'   => 'lang:new_password',
			),
			array(
				 'field'   => 'confirm_password',
				 'label'   => 'lang:confirm_password',
				 'rules'   => 'matches[password]'
			)
		));

		if (AJAX_REQUEST)
		{
			ee()->form_validation->run_ajax();
			exit;
		}
		elseif (ee()->form_validation->run() !== FALSE)
		{
			$this->register_member();
		}
		elseif (ee()->form_validation->errors_exist())
		{
			ee()->view->set_message('issue', lang('settings_save_error'), lang('settings_save_error_desc'));
		}

		ee()->view->base_url = $this->base_url;
		ee()->view->ajax_validate = TRUE;
		ee()->view->cp_page_title = lang('register_member');
		ee()->view->save_btn_text = 'btn_save_settings';
		ee()->view->save_btn_text_working = 'btn_saving';
		ee()->cp->render('settings/form', $vars);
	}

	/**
	 * Register Member
	 *
	 * Create a member profile
	 *
	 * @return	mixed
	 */
	private function register_member()
	{
		$this->load->helper('security');

		$data = array();

		if ($this->input->post('group_id'))
		{
			if ( ! $this->cp->allowed_group('can_admin_mbr_groups'))
			{
				show_error(lang('unauthorized_access'));
			}

			$data['group_id'] = $this->input->post('group_id');
		}

		// -------------------------------------------
		// 'cp_members_member_create_start' hook.
		//  - Take over member creation when done through the CP
		//  - Added 1.4.2
		//
			$this->extensions->call('cp_members_member_create_start');
			if ($this->extensions->end_script === TRUE) return;
		//
		// -------------------------------------------

		// If the screen name field is empty, we'll assign is
		// from the username field.

		$data['screen_name'] = ($this->input->post('screen_name')) ? $this->input->post('screen_name') : $this->input->post('username');

		// Get the password information from Auth
		$this->load->library('auth');
		$hashed_password = $this->auth->hash_password($this->input->post('password'));

		// Assign the query data
		$data['username'] 	= $this->input->post('username');
		$data['password']	= $hashed_password['password'];
		$data['salt']		= $hashed_password['salt'];
		$data['unique_id']	= random_string('encrypt');
		$data['crypt_key']	= $this->functions->random('encrypt', 16);
		$data['email']		= $this->input->post('email');
		$data['ip_address']	= $this->input->ip_address();
		$data['join_date']	= $this->localize->now;
		$data['language'] 	= $this->config->item('deft_lang');
		$data['timezone'] 	= $this->config->item('default_site_timezone');
		$data['date_format'] = $this->config->item('date_format') ? $this->config->item('date_format') : '%n/%j/%y';
		$data['time_format'] = $this->config->item('time_format') ? $this->config->item('time_format') : '12';
		$data['include_seconds'] = $this->config->item('include_seconds') ? $this->config->item('include_seconds') : 'n';

		// Was a member group ID submitted?

		$data['group_id'] = ( ! $this->input->post('group_id')) ? 2 : $_POST['group_id'];

		$base_fields = array('bday_y', 'bday_m', 'bday_d', 'url', 'location',
			'occupation', 'interests', 'aol_im', 'icq', 'yahoo_im', 'msn_im', 'bio');

		foreach ($base_fields as $val)
		{
			$data[$val] = ($this->input->post($val) === FALSE) ? '' : $this->input->post($val, TRUE);
		}

		if (is_numeric($data['bday_d']) && is_numeric($data['bday_m']))
		{
			$this->load->helper('date');
			$year = ($data['bday_y'] != '') ? $data['bday_y'] : date('Y');
			$mdays = days_in_month($data['bday_m'], $year);

			if ($data['bday_d'] > $mdays)
			{
				$data['bday_d'] = $mdays;
			}
		}

		// Clear out invalid values for strict mode
		foreach (array('bday_y', 'bday_m', 'bday_d') as $val)
		{
			if ($data[$val] == '')
			{
				unset($data[$val]);
			}
		}

		if ($data['url'] == 'http://')
		{
			$data['url'] = '';
		}

		// Extended profile fields
		$cust_fields = FALSE;
		$query = $this->member_model->get_all_member_fields(array(array('m_field_cp_reg' => 'y')), FALSE);

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $row)
			{
				if ($this->input->post('m_field_id_'.$row['m_field_id']) !== FALSE)
				{
					$cust_fields['m_field_id_'.$row['m_field_id']] = $this->input->post('m_field_id_'.$row['m_field_id'], TRUE);
				}
			}
		}

		$member_id = $this->member_model->create_member($data, $cust_fields);

		// Write log file

		$message = lang('new_member_added');
		$this->logger->log_action($message.NBS.NBS.stripslashes($data['username']));

		// -------------------------------------------
		// 'cp_members_member_create' hook.
		//  - Additional processing when a member is created through the CP
		//
			$this->extensions->call('cp_members_member_create', $member_id, $data);
			if ($this->extensions->end_script === TRUE) return;
		//
		// -------------------------------------------

		// Update Stats
		$this->stats->update_member_stats();
		$this->session->set_flashdata(array(
			'username' => stripslashes($data['screen_name'])
		));
		ee()->view->set_message('success', lang('member_updated'), lang('member_updated_desc'), TRUE);

		$this->functions->redirect(cp_url('members'));
	}
}
// END CLASS

/* End of file Create.php */
/* Location: ./system/expressionengine/controllers/cp/Members/Profile/Create.php */
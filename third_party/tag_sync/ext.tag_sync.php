<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
    This file is part of Tag Sync add-on for ExpressionEngine.

    Tag Sync is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Tag Sync is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    Read the terms of the GNU General Public License
    at <http://www.gnu.org/licenses/>.
    
    Copyright 2011 Derek Hogue
*/

class Tag_sync_ext
{
	var $settings			= array();
	var $name				= 'Tag Sync';
	var $version			= '2.0.1';
	var $description		= 'Synchronize Solspace Tag tags to a custom field when entries are publish or updated, or all at once.';
	var $settings_exist		= 'y';
	var $docs_url			= 'http://github.com/amphibian/tag_sync.ee2_addon';
	var $slug				= 'tag_sync';
	var $debug				= FALSE;
	var $batch_size			= 1000;


	function __construct($settings='')
	{
	    $this->settings = $settings;
	    $this->EE =& get_instance();
	}	

	
	function settings_form($current)
	{	    
		
		if($this->debug == TRUE)
		{
			print '<pre>';
			print_r($current);
			print '</pre>';
		}
		
		// Initialize our variable array
		$vars = array();		

		// We need our file name for the settings form
		$vars['file'] = $this->slug;
		
		// Get current site ID
		$site_id = $this->EE->config->item('site_id');
				
		// Add our current settings
		$vars['current'] =$current;
		
		// Get a list of channels for the current site
		$channels = $this->EE->db->query("SELECT channel_title, channel_id 
			FROM exp_channels 
			WHERE site_id = '".$this->EE->db->escape_str($site_id)."' 
			ORDER BY channel_title ASC");
		if($channels->num_rows() > 0)
		{			
			foreach($channels->result_array() as $value)
			{
				extract($value);
				$vars['channels'][$channel_id] = $channel_title;
				// Get fields for this channel
				$sql = "SELECT f.field_id, f.field_label 
					FROM exp_channels as c, exp_channel_fields as f 
					WHERE c.field_group = f.group_id
					AND f.field_type IN ('tag', 'text', 'textarea')
					AND c.channel_id = ".$this->EE->db->escape_str($channel_id)." 
					ORDER BY f.field_order ASC";
				$fields = $this->EE->db->query($sql);
				if($fields->num_rows() > 0)
				{
					$vars['fields'][$channel_id][] = '--';
					foreach($fields->result_array() as $value)
					{
						extract($value);
						$vars['fields'][$channel_id][$field_id] = $field_label;
					}
				}
			}
		}
		
		// Check to see if the Tag module is installed
		$installed = $this->EE->db->query("SELECT module_id 
			FROM exp_modules 
			WHERE module_name = 'Tag'");
		$vars['installed'] = ($installed->num_rows() > 0) ? 'y' : 'n';
		
		// Synchronize all tags to a specific channel's custom field
		if(isset($_GET['sync']) && !empty($current['channel_id_'.$_GET['sync']]) && $vars['installed'] == 'y')
		{
			$channel = $_GET['sync'];
			$custom_field = $current['channel_id_'.$channel];
			$current_batch = (isset($_GET['batch'])) ? $_GET['batch'] : 1;
			
			// Get count of entry IDs in this channel which have tags
			$sql = "SELECT COUNT(DISTINCT entry_id) as count FROM exp_tag_entries WHERE channel_id = ".$this->EE->db->escape_str($channel);
			$count = $this->EE->db->query($sql);
			
			if($count->row('count') > 0)
			{
				$batches = ceil($count->row('count') / $this->batch_size);
				$offset = ($current_batch - 1) * $this->batch_size;
				
				// Get the current batch of tagged entries to sync
				$sql = "SELECT DISTINCT entry_id FROM exp_tag_entries 
						WHERE channel_id = ".$this->EE->db->escape_str($channel)." 
						ORDER BY entry_id ASC LIMIT $offset, ".$this->batch_size;
						
				$entries = $this->EE->db->query($sql);
				$entry_ids = array();
				foreach($entries->result_array() as $result)
				{	
					$entry_ids[] = $result['entry_id'];
				}
				
				// Get all tags for this batch's entry_ids
				$sql = "SELECT DISTINCT t.tag_name, e.entry_id FROM exp_tag_entries AS e
						LEFT JOIN exp_tag_tags AS t ON e.tag_id = t.tag_id 
						WHERE e.entry_id IN('".implode("','", $this->EE->db->escape_str($entry_ids))."')
						ORDER BY e.entry_id ASC";
						
				$get_tags = $this->EE->db->query($sql);
					
				if($get_tags->num_rows() > 0)
				{
					// Create an array with each entry_id as a key,
					// and an array of its tags as a value
					$tags = array();
					foreach($get_tags->result_array() as $tag)
					{
						$tags[$tag['entry_id']][] = $tag['tag_name'];
					}

					// Build and run our update statement
					$sql = "UPDATE exp_channel_data SET `field_id_".ceil($custom_field)."` = CASE entry_id ";
					foreach($tags as $entry_id => $tag)
					{
						$sql .= "WHEN ".$entry_id." THEN '".$this->EE->db->escape_str(implode(' ', $tag))."' ";
					}
					$sql .= "END WHERE entry_id IN('".implode("','", $this->EE->db->escape_str($entry_ids))."')";
					
					$this->EE->db->query($sql);
					
					if($this->EE->db->affected_rows() == 0)
					{
						// Everything was already in sync ('N Sync?)
						$vars['message'] = $this->EE->lang->line('nothing_to_sync');
					}
					else
					{
						// We had updates
						$vars['message'] = $this->EE->db->affected_rows().' ';
						$vars['message'] .= ($this->EE->db->affected_rows() == 1) ? $this->EE->lang->line('entry') : $this->EE->lang->line('entries');
						$vars['message'] .= ' '.$this->EE->lang->line('synced_in_batch');
					}		
				}
				else
				{
					// None of the entries had tags - kinda redundant,
					// as we were only selecting entries with tags, but still...
					$vars['message'] = $this->EE->lang->line('nothing_to_sync');					
				}

				if($current_batch < $batches)
				{
					// We have more batches to run
					$vars['message'] .= ' <a href="'.
						BASE.AMP.'C=addons_extensions'.
						AMP.'M=extension_settings'.
						AMP.'file=tag_sync'.
						AMP.'sync='.$channel.
						AMP.'batch='.($current_batch + 1).'">'. 
						$this->EE->lang->line('run_batch').' '.($current_batch + 1).' '.$this->EE->lang->line('of').' '.$batches.
						'.</a>';
				}
				else
				{
					// We're all done!
					$vars['message'] .= ' <strong>'.$this->EE->lang->line('sync_complete').'</strong>';
				}
			}
			else
			{
				// The channel has no tagged entries
				$vars['message'] = $this->EE->lang->line('no_entries');
			}
		}
		// End synchronization routine		
		else
		{
			$vars['message'] = $this->EE->lang->line('tag_sync_warning');
		}
		
		// We have our vars set, so load and return the view file
		return $this->EE->load->view('settings', $vars, TRUE);		
	
	}
	
	
	function save_settings()
	{	
		
		// Get settings
		$settings = $this->get_settings();
				
		unset($_POST['file'], $_POST['submit']);
		
		// Add to the existing settings array
		foreach($_POST AS $channel => $field)
		{
			$settings[$channel] = round($field); // Insures that it is an integer
		}
		
		$this->EE->db->where('class', ucfirst(get_class($this)));
		$this->EE->db->update('extensions', array('settings' => serialize($settings)));
		
		$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('preferences_updated'));
	}

	
	function get_settings()
	{
		$get_settings = $this->EE->db->query("SELECT settings 
			FROM exp_extensions 
			WHERE class = '".ucfirst(get_class($this))."' 
			LIMIT 1");
		
		$this->EE->load->helper('string');
		
		if ($get_settings->num_rows() > 0 && $get_settings->row('settings') != '')
        {
        	$settings = strip_slashes(unserialize($get_settings->row('settings')));
        }
        else
        {
        	$settings = array();
        }
        return $settings;
	}
	

	function entry_submission_absolute_end($entry_id, $meta, $data)
	{        
        if(!empty($this->settings))
        {	        
	        if( isset($this->settings['channel_id_'.$meta['channel_id']]) && $this->settings['channel_id_'.$meta['channel_id']] != FALSE )
        	{
				$custom_field = $this->settings['channel_id_'.$meta['channel_id']];
				
				$sql = "SELECT DISTINCT t.tag_name FROM exp_tag_entries AS e LEFT JOIN exp_tag_tags AS t ON e.tag_id = t.tag_id WHERE e.entry_id = ".$entry_id;
				$get_tags = $this->EE->db->query($sql);
				
				if($get_tags->num_rows() > 0)
				{
					$tags = array();
					foreach($get_tags->result_array() as $tag)
					{
						$tags[] = $tag['tag_name'];
					}

					// Update the field with our list of tags
					$sql = "UPDATE exp_channel_data SET `field_id_".ceil($custom_field)."` = '".$this->EE->db->escape_str(implode(',', $tags))."' WHERE entry_id = $entry_id LIMIT 1";
				}
				else
				{
					// We don't have any tags, or just removed them all, so zero out the field
					$sql = "UPDATE exp_channel_data SET `field_id_".ceil($custom_field)."` = '' WHERE entry_id = $entry_id LIMIT 1";
				}
				$this->EE->db->query($sql);		
			}
		}	
	}  
	

	function activate_extension()
	{

	    $hooks = array(
	    	'entry_submission_absolute_end'
	    );
	    
	    foreach($hooks as $hook)
	    {
		    $this->EE->db->query($this->EE->db->insert_string('exp_extensions',
		    	array(
					'extension_id' => '',
			        'class'        => ucfirst(get_class($this)),
			        'method'       => $hook,
			        'hook'         => $hook,
			        'settings'     => '',
			        'priority'     => 10,
			        'version'      => $this->version,
			        'enabled'      => "y"
					)
				)
			);
	    }		
	}

   
	function update_extension($current='')
	{
	    if ($current == '' OR $current == $this->version)
	    {
	        return FALSE;
	    }
			
		$this->EE->db->query("UPDATE exp_extensions 
	     	SET version = '". $this->EE->db->escape_str($this->version)."' 
	     	WHERE class = '".ucfirst(get_class($this))."'");
	}

	
	function disable_extension()
	{	    
		$this->EE->db->query("DELETE FROM exp_extensions WHERE class = '".ucfirst(get_class($this))."'");
	}

}
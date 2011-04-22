<?php
    $this->EE =& get_instance();
   	$this->EE->cp->load_package_css('settings'); 
?>

<?php if($installed == 'y') : ?>

	<div class="tag_sync_message"><?php echo $message; ?></div>
	
	<?php
		echo form_open('C=addons_extensions'.AMP.'M=save_extension_settings', array('id' => $file), array('file' => $file));
	?>
	
	<table class="mainTable padTable" border="0" cellspacing="0" cellpadding="0">
		
		<thead>
			<tr>
				<th><?php echo $this->EE->lang->line('channel'); ?></th>
				<th><?php echo $this->EE->lang->line('tag_sync_tag_field'); ?></th>
				<th><?php echo $this->EE->lang->line('tag_sync_action'); ?></th>
			</tr>
		</thead>
	<?php
		$class = 'odd';
		foreach($channels as $channel_id => $channel_title) :
	?>
		<tbody>
			<tr class="<?php echo $class; ?>">
				<td>
					<?php echo $channel_title; ?>
				</td>
				<td>
					<?php if(isset($fields[$channel_id])) echo form_dropdown('channel_id_'.$channel_id, $fields[$channel_id], 
					(isset($current['channel_id_'.$channel_id])) ?$current['channel_id_'.$channel_id] : ''); ?>
				</td>
				<td>
					<?php if(isset($current['channel_id_'.$channel_id]) && $current['channel_id_'.$channel_id] != FALSE) : ?>
					<a href="<?php echo BASE.AMP.'C=addons_extensions'.AMP.'M=extension_settings'.AMP.'file=tag_sync'.AMP.'sync='.$channel_id; ?>"><?php echo $this->EE->lang->line('tag_sync_sync'); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	<?php $class = ($class == 'odd') ? 'even' : 'odd'; endforeach; ?>		
	</table>
		
	<?php	
		echo form_submit(array('name' => 'submit', 'value' => $this->EE->lang->line('save_settings'), 'class' => 'submit'));
		echo form_close();
	?>
	
<?php else : ?>

	<p><?php echo $this->EE->lang->line('tag_not_installed');?></p>

<?php endif; ?>
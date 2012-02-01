<?php
/* @var $page_data PageData */
$page_data->head->title = tr('Edit your account settings', 'users');
$page_data->breadcrumb = WidgetBreadcrumb::output(array(
	tr('Change Account Data', 'users')
))
?>
<h1><?=tr('Change Account Data', 'users')?></h1>

<p><?php print tr('Fill out the fields below and click <strong>Save</strong> to save your data.', 'users');?></p>

<form class="has_focus" id="frmeditaccount" name="frmeditaccount" action="<?=ActionMapper::get_path('users_edit_self')?>" method="post">
 	<?php print $form_validation; ?>

	<fieldset>
	<legend><?=tr('User Data', 'users')?></legend>

	<?php print WidgetInput::output('name', tr('Username:', 'users'), $form_data) ?>
	<?php print WidgetInput::output('email', tr('E-mail:', 'users'), $form_data) ?>
	<?php print WidgetInput::output('pwd_mail', tr('Please confirm changes of the email address by entering the password:','users'), '', WidgetInput::PASSWORD, array('autocomplete' => 'off')); ?>

	<p class="important">
	<?php print tr('If the e-mail changes, you will get a mail send to the new address to confirm this address exists.', 'users')?></p>

	<?php print WidgetInput::output('pwd1', tr('Password:', 'users'), $form_data, WidgetInput::PASSWORD) ?>
	<?php print WidgetInput::output('pwd2', tr('Repeat Password:', 'users'), $form_data, WidgetInput::PASSWORD) ?>

	<p><?php print tr('Leave these fields empty to not change the password.',  'users')?></p> 
	<p class="important">
	<?php print tr('If the password changes, you will get a mail send to you to confirm this change.', 'users')?></p>
	</fieldset>

	<?php print WidgetInput::output('submit', '', tr('Save', 'users'), WidgetInput::SUBMIT); ?>
</form>

<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
HTMLHelper::_('behavior.formvalidator');
?>
<div class="newsletterbridge" data-nb-editor data-id="<?= (int) $this->item->id ?>">
<h1><?= Text::_($this->item->id ? 'COM_NEWSLETTERBRIDGE_EDIT' : 'COM_NEWSLETTERBRIDGE_NEW') ?></h1>
<p><?= Text::_('COM_NEWSLETTERBRIDGE_EDITOR_HELP') ?></p>
<div class="nb-presets">
<label for="nb-preset"><?= Text::_('COM_NEWSLETTERBRIDGE_PRESET') ?></label>
<select id="nb-preset"><option value=""><?= Text::_('COM_NEWSLETTERBRIDGE_SELECT_PRESET') ?></option>
<?php foreach ($this->presets as $preset) : ?><option value="<?= $this->escape($preset['id']) ?>"><?= $this->escape(Text::_('COM_NEWSLETTERBRIDGE_PRESET_' . str_replace('-', '_', strtoupper($preset['id'])))) ?></option><?php endforeach; ?>
</select><button class="btn btn-secondary" type="button" data-nb-preset><?= Text::_('COM_NEWSLETTERBRIDGE_APPLY_PRESET') ?></button>
</div>
<p data-nb-message role="status" aria-live="polite"></p>
<form action="<?= $this->url('newsletter', (int) $this->item->id) ?>" method="post" id="nb-form" class="form-validate">
<?= $this->form->renderField('title') ?>
<?= $this->form->renderField('subject') ?>
<?= $this->form->renderField('body') ?>
<?php if ($this->policy->allows('core.edit.state')) : ?><?= $this->form->renderField('published') ?><?php endif; ?>
<p class="nb-placeholders">{name}, {email}, {unsubscribe_link}, {confirm_link}, {sitename}, {siteurl}, {date}</p>
<div class="nb-actions">
<button class="btn btn-primary" type="submit" name="task" value="newsletter.save"><?= Text::_('COM_NEWSLETTERBRIDGE_SAVE') ?></button>
<button class="btn btn-secondary" type="submit" name="task" value="newsletter.cancel" formnovalidate><?= Text::_('COM_NEWSLETTERBRIDGE_CANCEL') ?></button>
</div>
<input type="hidden" name="id" value="<?= (int) $this->item->id ?>">
<input type="hidden" name="return" value="<?= $this->escape($this->returnUrl()) ?>">
<?= HTMLHelper::_('form.token') ?>
</form>
</div>

<?php \Joomla\CMS\Factory::getApplication()->getDocument()->addScriptOptions('com_newsletterbridge.labels', ['preset_confirm' => Text::_('COM_NEWSLETTERBRIDGE_PRESET_CONFIRM'), 'network_error' => Text::_('COM_NEWSLETTERBRIDGE_NETWORK_ERROR')]); ?>

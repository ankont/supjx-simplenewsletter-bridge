<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
$userId = (int) Factory::getApplication()->getIdentity()->id;
?>
<div class="newsletterbridge">
<h1><?= Text::_('COM_NEWSLETTERBRIDGE_MENU_TITLE') ?></h1>
<?php if ($this->policy->allows('core.create')) : ?><a class="btn btn-primary" href="<?= $this->url('newsletter') ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_NEW') ?></a><?php endif; ?>
<form action="<?= $this->url() ?>" method="get" class="nb-search">
<input type="hidden" name="option" value="com_newsletterbridge"><input type="hidden" name="view" value="newsletters">
<input type="hidden" name="Itemid" value="<?= Factory::getApplication()->getInput()->getInt('Itemid') ?>">
<label for="nb-search"><?= Text::_('COM_NEWSLETTERBRIDGE_SEARCH') ?></label>
<input type="search" id="nb-search" name="search" maxlength="100" value="<?= $this->escape($this->state->get('filter.search')) ?>">
<button class="btn btn-secondary" type="submit"><?= Text::_('COM_NEWSLETTERBRIDGE_SEARCH') ?></button>
</form>
<?php if (!$this->items) : ?><p><?= Text::_('COM_NEWSLETTERBRIDGE_EMPTY') ?></p><?php else : ?>
<div class="nb-table"><table><caption><?= Text::_('COM_NEWSLETTERBRIDGE_NEWSLETTERS') ?></caption><thead><tr>
<th scope="col"><?= Text::_('COM_NEWSLETTERBRIDGE_TITLE') ?></th><th scope="col"><?= Text::_('COM_NEWSLETTERBRIDGE_SUBJECT') ?></th><th scope="col"><?= Text::_('JSTATUS') ?></th><th scope="col"><?= Text::_('COM_NEWSLETTERBRIDGE_ACTIONS') ?></th>
</tr></thead><tbody>
<?php foreach ($this->items as $item) : ?>
<tr><td><?= $this->escape($item->title) ?></td><td><?= $this->escape($item->subject) ?></td><td><?= Text::_((int) $item->published ? 'JPUBLISHED' : 'JUNPUBLISHED') ?></td><td><div class="nb-actions">
<?php if ($this->policy->edit($item)) : ?>
<form action="<?= $this->url() ?>" method="post">
<button class="btn btn-secondary" type="submit" name="task" value="newsletter.edit" <?= ((int) $item->checked_out && (int) $item->checked_out !== $userId) ? 'disabled' : '' ?>><?= Text::_('COM_NEWSLETTERBRIDGE_EDIT') ?></button>
<input type="hidden" name="id" value="<?= (int) $item->id ?>"><?= HTMLHelper::_('form.token') ?>
</form>
<?php if ((int) $item->checked_out) : ?><span><?= Text::_('COM_NEWSLETTERBRIDGE_CHECKED_OUT') ?></span><?php endif; ?>
<?php endif; ?>
<a class="btn btn-secondary" href="<?= $this->url('preview', (int) $item->id) ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_PREVIEW') ?></a>
<?php if ($this->policy->archive($item)) : ?><a class="btn btn-secondary" href="<?= $this->url('publications', (int) $item->id) ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_MANAGE') ?></a><?php endif; ?>
<?php if ($this->policy->send($item)) : ?><a class="btn btn-secondary" href="<?= $this->url('send', (int) $item->id) ?>"><?= Text::_('COM_NEWSLETTERBRIDGE_SEND') ?></a><?php endif; ?>
<?php if ($this->policy->edit($item) && $this->policy->allows('core.edit.state')) : ?>
<form action="<?= $this->url() ?>" method="post">
<button class="btn btn-secondary" type="submit" name="task" value="newsletter.state"><?= Text::_((int) $item->published ? 'COM_NEWSLETTERBRIDGE_UNPUBLISH' : 'COM_NEWSLETTERBRIDGE_PUBLISH') ?></button>
<input type="hidden" name="id" value="<?= (int) $item->id ?>"><input type="hidden" name="published" value="<?= (int) $item->published ? 0 : 1 ?>"><?= HTMLHelper::_('form.token') ?>
</form><?php endif; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<form action="<?= $this->url() ?>" method="post" id="adminForm" name="adminForm">
<input type="hidden" name="search" value="<?= $this->escape($this->state->get('filter.search')) ?>">
<?= $this->pagination->getListFooter() ?>
</form>
<?php endif; ?>
</div>

<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="newsletterbridge">
<h1><?= Text::_('COM_NEWSLETTERBRIDGE_PUBLIC_TITLE') ?></h1>
<section aria-labelledby="nb-subscribe-title">
<h2 id="nb-subscribe-title"><?= Text::_('COM_NEWSLETTERBRIDGE_SUBSCRIBE') ?></h2>
<p><?= Text::_('COM_NEWSLETTERBRIDGE_SUBSCRIBE_HELP') ?></p>
<form action="<?= Route::_('index.php?option=com_simplenewsletter&task=subscribe.subscribe') ?>" method="post" class="nb-subscribe">
<?php if ($this->showName) : ?><div><label for="nb-name"><?= Text::_('COM_NEWSLETTERBRIDGE_NAME') ?></label><input class="form-control" id="nb-name" name="name" maxlength="255" autocomplete="name"></div><?php endif; ?>
<div><label for="nb-email"><?= Text::_('COM_NEWSLETTERBRIDGE_EMAIL') ?></label><input class="form-control" type="email" id="nb-email" name="email" maxlength="190" autocomplete="email" required></div>
<div class="nb-honeypot" aria-hidden="true"><label for="nb-hp"><?= Text::_('COM_NEWSLETTERBRIDGE_LEAVE_EMPTY') ?></label><input id="nb-hp" name="nl_hp" tabindex="-1" autocomplete="off"></div>
<input type="hidden" name="return" value="<?= $this->escape($this->return) ?>">
<?= HTMLHelper::_('form.token') ?>
<button class="btn btn-primary" type="submit"><?= Text::_('COM_NEWSLETTERBRIDGE_SUBSCRIBE') ?></button>
</form>
</section>
<section aria-labelledby="nb-archive-title">
<h2 id="nb-archive-title"><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE') ?></h2>
<?php if (!$this->items) : ?><p><?= Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_EMPTY') ?></p><?php else : ?>
<ul class="nb-archive-list">
<?php foreach ($this->items as $item) : ?><li><a href="<?= $this->url('issue', (int) $item->job_id) ?>"><?= $this->escape($item->title) ?></a> <span><?= $this->escape($item->sent_at ? HTMLHelper::_('date', $item->sent_at, Text::_('DATE_FORMAT_LC3')) : '') ?></span></li><?php endforeach; ?>
</ul>
<form action="<?= $this->url() ?>" method="post" id="adminForm" name="adminForm"><?= $this->pagination->getPagesLinks() ?></form>
<?php endif; ?>
</section>
</div>

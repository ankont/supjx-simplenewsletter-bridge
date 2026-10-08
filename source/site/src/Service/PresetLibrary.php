<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use SaintArt\Component\SimpleNewsletter\Administrator\Helper\PresetHelper;

/** Category-scoped Joomla articles, with independent newsletter subject metadata. */
final class PresetLibrary
{
    private $app; private $db; private $categoryId; private $factory;
    public function __construct($app, $db, ?int $categoryId = null, $factory = null)
    {
        $this->app = $app; $this->db = $db;
        $this->categoryId = $categoryId ?? (int) ComponentHelper::getParams('com_newsletterbridge')->get('preset_category', 0);
        $this->factory = $factory;
    }
    private function user() { return $this->app->getIdentity(); }
    private function category(bool $lock = false): ?object
    {
        if ($this->categoryId < 1) { return null; }
        $this->db->setQuery('SELECT id,access,published FROM #__categories WHERE id=' . $this->categoryId
            . ' AND extension=' . $this->db->quote('com_content') . ' AND published IN (0,1)' . ($lock ? ' FOR UPDATE' : ''));
        $category = $this->db->loadObject();
        if (!$category || !in_array((int) $category->access, $this->user()->getAuthorisedViewLevels(), true)) { return null; }
        $asset = 'com_content.category.' . $category->id;
        if ((int) $category->published === 0 && !$this->user()->authorise('core.create', $asset)
            && !$this->user()->authorise('core.edit', $asset) && !$this->user()->authorise('core.edit.own', $asset)) { return null; }
        return $category;
    }
    public function builtins(): array
    {
        $translations = [];
        foreach (['en-GB','el-GR'] as $language) {
            $file = JPATH_SITE . '/language/' . $language . '/com_newsletterbridge.ini';
            if (is_readable($file)) { $translations[] = parse_ini_file($file, false, INI_SCANNER_RAW) ?: []; }
        }
        $presets = [];
        foreach (PresetHelper::getPresets() as $preset) {
            $key = 'COM_NEWSLETTERBRIDGE_PRESET_' . str_replace('-', '_', strtoupper($preset['id']));
            $label = Text::_($key);
            if ($label === $key) { $label = Text::_($preset['title']); }
            $names = [$label];
            foreach ($translations as $strings) { if (isset($strings[$key])) { $names[] = $strings[$key]; } }
            $presets[] = $preset + ['label' => $label, 'names' => array_unique($names)];
        }
        return $presets;
    }
    private function rows(): array
    {
        if (!$this->category()) { return []; }
        // No body is read before resource and view-level ACL checks.
        $this->db->setQuery('SELECT a.id,a.title,a.state,a.access,a.created_by,a.checked_out,m.builtin_id,m.name AS saved_name'
            . ' FROM #__content a LEFT JOIN #__newsletterbridge_presets m ON m.article_id=a.id WHERE a.catid=' . $this->categoryId
            . ' AND a.state IN (0,1) ORDER BY a.id DESC');
        return $this->db->loadObjectList() ?: [];
    }
    private function edit(object $article): bool
    {
        $user = $this->user(); $asset = 'com_content.article.' . (int) $article->id;
        return $user->authorise('core.edit', $asset)
            || ((int) $article->created_by === (int) $user->id && $user->authorise('core.edit.own', $asset));
    }
    private function read(object $article): bool
    {
        return in_array((int) $article->access, $this->user()->getAuthorisedViewLevels(), true)
            && ((int) $article->state === 1 || $this->edit($article));
    }
    public function entries(): array
    {
        return PresetNames::merge($this->builtins(), array_values(array_filter($this->rows(), fn ($row) => $this->read($row))));
    }
    public function choices(): array
    {
        return array_map(fn ($entry) => ['id' => $entry['id'], 'title' => $entry['title']], $this->entries());
    }
    public function canSave(): bool
    {
        $category = $this->category();
        if (!$category) { return false; }
        if ($this->user()->authorise('core.create', 'com_content.category.' . $category->id)) { return true; }
        foreach ($this->rows() as $row) { if ($this->read($row) && $this->edit($row)) { return true; } }
        return false;
    }
    public function get(string $id): array
    {
        foreach ($this->entries() as $entry) {
            if ($entry['id'] !== $id) { continue; }
            if (!$entry['article_id']) {
                foreach (PresetHelper::getPresets() as $preset) {
                    if ($preset['id'] === $id) { return ['body' => ComponentHelper::filterText(PresetHelper::getBody($id)), 'subject' => $preset['subject']]; }
                }
            } else {
                $this->db->setQuery('SELECT a.id,a.title,a.introtext,a.fulltext,a.state,a.access,a.created_by,m.subject'
                    . ' FROM #__content a LEFT JOIN #__newsletterbridge_presets m ON m.article_id=a.id WHERE a.id=' . (int) $entry['article_id']
                    . ' AND a.catid=' . $this->categoryId . ' AND a.state IN (0,1)');
                $article = $this->db->loadObject();
                if (!$article || !$this->category() || !$this->read($article)) { break; }
                return ['body' => ComponentHelper::filterText($article->introtext . $article->fulltext), 'subject' => $article->subject ?? $article->title];
            }
        }
        throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404);
    }
    public function save(string $name, string $subject, string $body): array
    {
        if (!$this->category()) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_PRESET_CATEGORY_UNAVAILABLE', 403); }
        $data = InputGuard::newsletter(['title' => $name, 'subject' => $subject, 'body' => $body]);
        $data['body'] = ComponentHelper::filterText($data['body']);
        $data = InputGuard::newsletter($data);
        $builtins = $this->builtins(); $target = PresetNames::builtin($data['title'], $builtins);
        // Joomla nested asset operations use LOCK TABLES, which may implicitly commit.
        // A connection-scoped lock therefore protects the whole category across model save.
        $lock = 'nb:preset:' . substr(hash('sha256', $this->db->getPrefix() . ':' . $this->categoryId), 0, 40);
        if ((int) $this->db->setQuery('SELECT GET_LOCK(' . $this->db->quote($lock) . ',0)')->loadResult() !== 1) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_BUSY', 409);
        }
        $model = null; $checkedOut = false; $id = 0;
        $before = null; $beforeMetadata = null; $alias = null;
        try {
            $this->db->transactionStart();
            // Recheck category and resource ACL under the category lock.
            $category = $this->category(true);
            if (!$category) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_PRESET_CATEGORY_UNAVAILABLE', 403); }
            $matches = array_filter($this->rows(), function ($row) use ($data, $builtins, $target) {
                return PresetNames::key($row->title) === PresetNames::key($data['title'])
                    || ($target !== null && PresetNames::target($row, $builtins) === $target);
            });
            if (count($matches) > 1) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_PRESET_DUPLICATE', 409); }
            $existing = $matches ? reset($matches) : null;
            if ($existing) {
                $id = (int) $existing->id;
                $this->db->setQuery('SELECT id,title,state,access,created_by,checked_out FROM #__content WHERE id=' . $id . ' AND catid=' . $this->categoryId . ' FOR UPDATE');
                $existing = $this->db->loadObject();
                if (!$existing || !$this->read($existing) || !$this->edit($existing)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_FORBIDDEN', 403); }
                // Never check in a lock belonging to another editor, or steal a same-user tab's lock.
                if ((int) $existing->checked_out !== 0) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOCKED', 409); }
            } elseif (!$this->user()->authorise('core.create', 'com_content.category.' . $category->id)) {
                throw new \RuntimeException('COM_NEWSLETTERBRIDGE_FORBIDDEN', 403);
            }
            if ($id) {
                $this->db->setQuery('SELECT * FROM #__content WHERE id=' . $id);
                $before = $this->db->loadObject();
                $this->db->setQuery('SELECT * FROM #__newsletterbridge_presets WHERE article_id=' . $id);
                $beforeMetadata = $this->db->loadObject();
            }
            $factory = $this->factory ?? $this->app->bootComponent('com_content')->getMVCFactory();
            $model = $factory->createModel('Article', 'Administrator', ['ignore_request' => true]);
            $model->setCurrentUser($this->user());
            $model->setState('article.id', $id);
            Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_content/forms');
            if ($id) {
                if (!$model->checkout($id)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOCKED', 409); }
                $checkedOut = true;
            }
            $safe = ['id' => $id, 'title' => $data['title'], 'catid' => $this->categoryId, 'articletext' => $data['body']];
            if (!$id) {
                $alias = 'nb-preset-' . bin2hex(random_bytes(12));
                $safe += ['alias' => $alias, 'state' => 0,
                    'access' => (int) $category->access === 1 ? 2 : (int) $category->access,
                    'created_by' => (int) $this->user()->id, 'language' => '*', 'featured' => 0];
            }
            // Joomla's ArticleModel retains assets, history, workflow association and content events.
            // No transition, rules, category change, owner, access or state comes from user input.
            if (!$model->save($safe)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_PRESET_SAVE_FAILED', 500); }
            $id = (int) $model->getState('article.id');
            if (!$id || ($checkedOut && !$model->checkin($id))) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOCKED', 409); }
            $metadata = (object) ['article_id' => $id, 'name' => $data['title'], 'builtin_id' => $target, 'subject' => $data['subject']];
            $this->db->setQuery('SELECT article_id FROM #__newsletterbridge_presets WHERE article_id=' . $id);
            if ($this->db->loadResult()) { $this->db->updateObject('#__newsletterbridge_presets', $metadata, 'article_id', true); }
            else { $this->db->insertObject('#__newsletterbridge_presets', $metadata); }
            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();
            // Compensate for Joomla's implicit asset commits. Never touch a foreign resource.
            if ($before) {
                $this->db->updateObject('#__content', $before, 'id', true);
                $asset = (object) ['id' => (int) $before->asset_id, 'title' => $before->title];
                if ($asset->id) { $this->db->updateObject('#__assets', $asset, 'id'); }
                $this->db->setQuery('DELETE FROM #__newsletterbridge_presets WHERE article_id=' . (int) $before->id)->execute();
                if ($beforeMetadata) { $this->db->insertObject('#__newsletterbridge_presets', $beforeMetadata); }
            } elseif ($alias !== null) {
                $this->db->setQuery('SELECT id FROM #__content WHERE catid=' . $this->categoryId
                    . ' AND alias=' . $this->db->quote($alias) . ' AND created_by=' . (int) $this->user()->id);
                $partial = (int) $this->db->loadResult();
                if ($partial) {
                    $this->db->setQuery('DELETE FROM #__newsletterbridge_presets WHERE article_id=' . $partial)->execute();
                    $this->db->setQuery('DELETE FROM #__workflow_associations WHERE item_id=' . $partial . ' AND extension=' . $this->db->quote('com_content.article'))->execute();
                    $table = $this->app->bootComponent('com_content')->getMVCFactory()->createTable('Article', 'Administrator');
                    if ($table->load($partial)) { $table->delete($partial); }
                }
            }
            throw $e;
        } finally {
            $this->db->setQuery('SELECT RELEASE_LOCK(' . $this->db->quote($lock) . ')')->loadResult();
        }
        return ['presets' => $this->choices(), 'preset' => $target ?? 'article-' . $id];
    }
}

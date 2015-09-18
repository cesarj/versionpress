<?php
namespace VersionPress\Storages;

use Nette\Utils\Strings;
use VersionPress\ChangeInfos\OptionChangeInfo;
use VersionPress\Database\EntityInfo;
use VersionPress\Utils\IniSerializer;

class OptionDirectoryStorage extends DirectoryStorage {

    const PREFIX_PLACEHOLDER = "<<table-prefix>>";

    public static $optionBlacklist = array(
        'cron',          // Cron, siteurl and home are specific for environment, so they're not saved, too.
        'home',
        'siteurl',
        'db_upgraded',
        'recently_edited',
        'auto_updater.lock',
        'can_compress_scripts',
        'auto_core_update_notified',
    );

    /** @var EntityInfo */
    private $entityInfo;
    /** @var string */
    private $tablePrefix;

    public function __construct($directory, $entityInfo, $tablePrefix) {
        parent::__construct($directory, $entityInfo);
        $this->entityInfo = $entityInfo;
        $this->tablePrefix = $tablePrefix;
    }

    protected function createChangeInfo($oldEntity, $newEntity, $action = null) {
        return new OptionChangeInfo($action, $newEntity['option_name']);
    }

    protected function serializeEntity($optionName, $entity) {
        $optionName = $this->maybeReplacePrefixWithPlaceholder($optionName);
        return parent::serializeEntity($optionName, $entity);
    }

    protected function deserializeEntity($serializedEntity) {
        $entity = IniSerializer::deserializeFlat($serializedEntity);
        $flatEntity = $this->flattenEntity($entity);
        if (isset($flatEntity[$this->entityInfo->idColumnName])) {
            $flatEntity[$this->entityInfo->idColumnName] = $this->maybeReplacePlaceholderWithPrefix($flatEntity[$this->entityInfo->idColumnName]);
        }
        return $flatEntity;
    }

    public function shouldBeSaved($data) {
        $id = $data[$this->entityInfo->idColumnName];
        return !($this->isTransientOption($id) || in_array($id, self::$optionBlacklist));
    }

    private function isTransientOption($id) {
        return substr($id, 0, 1) === '_'; // All transient options begin with underscore - there's no need to save them
    }

    private function maybeReplacePrefixWithPlaceholder($key) {
        if (Strings::startsWith($key, $this->tablePrefix)) {
            return self::PREFIX_PLACEHOLDER . Strings::substring($key, Strings::length($this->tablePrefix));
        }
        return $key;
    }

    private function maybeReplacePlaceholderWithPrefix($key) {
        if (Strings::startsWith($key, self::PREFIX_PLACEHOLDER)) {
            return $this->tablePrefix . Strings::substring($key, Strings::length(self::PREFIX_PLACEHOLDER));
        }
        return $key;
    }
}

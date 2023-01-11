<?php

namespace ImportWPAddon\BLMReader\Importer\Parser;

use ImportWP\Common\Importer\Parser\AbstractParser;
use ImportWP\Common\Importer\ParserInterface;
use ImportWPAddon\BLMReader\Importer\File\BLMFile;

/**
 * @property BLMFile $file
 */
class BLMParser extends AbstractParser implements ParserInterface
{
    /**
     * BLM Record
     *
     * @var array
     */
    private $blm_record;

    public function query($query)
    {
        // convert $query to column number
        $map = $this->file->getMap();

        $index = array_search($query, $map);

        return $index !== false ? $this->blm_record[$index] : '';
    }

    protected function onRecordLoaded()
    {
        $this->blm_record = explode($this->file->getEOF(), trim($this->record));
    }

    public function record()
    {
        return $this->blm_record;
    }
}

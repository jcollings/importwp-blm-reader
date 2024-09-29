<?php

namespace ImportWPAddon\BLMReader\Importer\File;

use ImportWP\Common\Importer\File\AbstractIndexedFile;
use ImportWP\Common\Importer\FileInterface;

class BLMFile extends AbstractIndexedFile implements FileInterface
{

    protected $chunk_size = 8192;

    public function getEOF()
    {
        return $this->config->get('eof');
    }

    public function getEOR()
    {
        return $this->config->get('eor');
    }

    public function getMap()
    {
        return $this->config->get('definition');
    }

    /**
     * Generate record file positions
     *
     * Loop through each record and save each position
     */
    protected function generateIndex()
    {
        $this->fetch_blm_data();

        rewind($this->getFileHandle());

        $sections = $this->config->get('sections');

        fseek($this->getFileHandle(), $sections['DATA']['start']);
        $remaining_bytes = $sections['DATA']['length'];

        $record = 0;
        $file_offset = $startIndex = ftell($this->getFileHandle());

        $data = '';
        $eor = $this->getEOR();

        // Escape EOR
        if ($eor == "|") {
            $eor = "\|";
        }

        do {
            $chunk_size = $this->chunk_size < $remaining_bytes ? $this->chunk_size : $remaining_bytes;
            $data .= fread($this->getFileHandle(), $chunk_size);
            $remaining_bytes -= $chunk_size;

            while (preg_match('/' . $eor . '/m', $data, $matches, PREG_OFFSET_CAPTURE) !== 0) {
                list($captured, $offset) = $matches[0];

                $tag_length = strlen($captured);
                $string_offset = $offset + $tag_length;
                $file_offset += $string_offset;

                $this->setIndex($record, $startIndex, $startIndex + $offset);
                $startIndex = $file_offset;
                $record++;

                $data = substr($data, $string_offset);
            }

            if ($this->is_processing && ($record >= 2 || $startIndex > $this->process_max_size)) {
                break;
            }
        } while ($remaining_bytes > 0);
    }

    protected function fetch_blm_data()
    {
        $fh = $this->getFileHandle();
        rewind($fh);

        $sections = [
            'HEADER' => false,
            'DEFINITION' => false,
            'DATA' => false,
            'END' => false,
        ];

        $data = '';
        $file_offset = 0;
        $test = '';

        while (!feof($this->getFileHandle())) {

            $data .= fread($this->getFileHandle(), $this->chunk_size);
            $test .= $data;

            $remaining_sections = array_filter($sections, function ($item) {
                return !$item;
            });

            foreach (array_keys($remaining_sections) as $section) {

                if (preg_match('/^(#' . $section . '#.*)$/m', $data, $matches, PREG_OFFSET_CAPTURE) !== 0) {
                    list($captured, $offset) = $matches[0];

                    $tag_length = strlen($captured);
                    $string_offset = $offset + $tag_length;
                    $file_offset += $string_offset;

                    $sections[$section] = [
                        'tag' => $file_offset - $tag_length,
                        'start' => $file_offset + 1 // take into account the new line
                    ];

                    $data = substr($data, $string_offset);
                }
            }
        }

        $missing_sections = array_filter($sections, function ($item) {
            return $item;
        });

        if (!empty($missing_sections)) {
            // Error file is not complete
        }

        $previous = false;
        foreach ($sections as $id => $data) {

            if ($previous) {
                $sections[$previous]['length'] = $data['tag'] - $sections[$previous]['start'];
            }

            $previous = $id;
        }

        $this->config->set('sections', $sections);

        fseek($this->getFileHandle(), $sections['HEADER']['start']);
        $header = trim(fread($this->getFileHandle(), $sections['HEADER']['length']));

        $version = $this->get_header_value('Version', $header);
        $this->config->set('version', $version);

        $eof = $this->get_header_value('EOF', $header);
        if (strlen($eof) > 1) {
            $eof = substr($eof, 1, 1);
        }

        $this->config->set('eof', $eof);

        $eor = $this->get_header_value('EOR', $header);
        if (strlen($eor) > 1) {
            $eor = substr($eor, 1, 1);
        }

        $this->config->set('eor', $eor);

        $property_count = $this->get_header_value('Property Count', $header);
        $this->config->set('property_count', $property_count);

        $generated_date = $this->get_header_value('Generated Date', $header);
        $this->config->set('generated_date', $generated_date);


        // 2. Read DEFINITION data to get 

        fseek($this->getFileHandle(), $sections['DEFINITION']['start']);
        $definition = trim(fread($this->getFileHandle(), $sections['DEFINITION']['length'] - 1));
        $this->config->set('definition', explode($this->config->get('eof'), substr($definition, 0, -1)));
    }

    public function get_header_value($key, $data)
    {
        if (preg_match('/^' . $key . '[^:]:\s*(.+)$/m', $data, $matches) !== false) {
            return isset($matches[1]) ? trim($matches[1]) : false;
        }

        return false;
    }
}

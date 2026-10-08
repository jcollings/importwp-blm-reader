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
        $eor_length = strlen($eor);

        do {
            $chunk_size = $this->chunk_size < $remaining_bytes ? $this->chunk_size : $remaining_bytes;
            $data .= fread($this->getFileHandle(), $chunk_size);
            $remaining_bytes -= $chunk_size;

            while ($eor_length > 0 && ($offset = strpos($data, $eor)) !== false) {
                $string_offset = $offset + $eor_length;
                $file_offset += $string_offset;

                $this->setIndex($record, $startIndex, $startIndex + $offset);
                $startIndex = $file_offset;
                $record++;

                $data = substr($data, $string_offset);

                if ($this->is_processing && ($record >= 2 || $startIndex > $this->process_max_size)) {
                    break 2;
                }
            }

            if ($this->is_processing && $startIndex > $this->process_max_size) {
                break;
            }
        } while ($remaining_bytes > 0);
    }

    protected function fetch_blm_data()
    {
        $fh = $this->getFileHandle();
        rewind($fh);

        $section_names = array('HEADER', 'DEFINITION', 'DATA', 'END');
        $sections = array(
            'HEADER' => false,
            'DEFINITION' => false,
            'DATA' => false,
            'END' => false,
        );

        $carry = '';
        $position = 0;

        while (!feof($fh)) {
            $chunk = fread($fh, $this->chunk_size);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer = $carry . $chunk;
            $buffer_base = $position - strlen($carry);
            $position += strlen($chunk);

            foreach ($section_names as $section) {
                if ($sections[$section]) {
                    continue;
                }

                $located = $this->locate_section_marker($buffer, $section, $buffer_base);
                if ($located) {
                    $sections[$section] = $located;
                }
            }

            if (!in_array(false, $sections, true)) {
                break;
            }

            $carry = $this->section_scan_carry($buffer);
        }

        if ($carry !== '' && in_array(false, $sections, true)) {
            $buffer_base = $position - strlen($carry);
            foreach ($section_names as $section) {
                if ($sections[$section]) {
                    continue;
                }

                $located = $this->locate_section_marker($carry, $section, $buffer_base, true);
                if ($located) {
                    $sections[$section] = $located;
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

    /**
     * Locate a BLM section marker at the start of a line.
     *
     * The marker line must end in a newline unless this is the end of the file,
     * so a tag split across two reads is completed on the next chunk.
     *
     * @param string $buffer
     * @param string $section
     * @param int $buffer_base Absolute file offset of the first buffer byte.
     * @param bool $allow_eof Accept a marker that has no trailing newline.
     * @return array|null
     */
    private function locate_section_marker($buffer, $section, $buffer_base, $allow_eof = false)
    {
        $needle = '#' . $section . '#';
        $needle_length = strlen($needle);
        $length = strlen($buffer);
        $offset = 0;

        while ($offset <= $length - $needle_length) {
            $pos = strpos($buffer, $needle, $offset);
            if ($pos === false) {
                return null;
            }

            $at_line_start = $pos === 0 || $buffer[$pos - 1] === "\n";
            if (!$at_line_start) {
                $offset = $pos + 1;
                continue;
            }

            $line_end = strpos($buffer, "\n", $pos);
            if ($line_end === false) {
                if (!$allow_eof) {
                    return null;
                }

                return array(
                    'tag' => $buffer_base + $pos,
                    'start' => $buffer_base + $length,
                );
            }

            return array(
                'tag' => $buffer_base + $pos,
                'start' => $buffer_base + $line_end + 1,
            );
        }

        return null;
    }

    /**
     * Keep only an unfinished line that could still be a section marker.
     *
     * @param string $buffer
     * @return string
     */
    private function section_scan_carry($buffer)
    {
        $last_newline = strrpos($buffer, "\n");
        $tail = $last_newline === false ? $buffer : substr($buffer, $last_newline + 1);

        if ($tail === '') {
            return '';
        }

        $max_marker = strlen('#DEFINITION#');
        if (strlen($tail) <= $max_marker) {
            return $tail;
        }

        foreach (array('#HEADER#', '#DEFINITION#', '#DATA#', '#END#') as $marker) {
            if (strpos($tail, $marker) === 0) {
                return $tail;
            }
        }

        return '';
    }

    public function get_header_value($key, $data)
    {
        if (preg_match('/^' . $key . '[^:]:\s*(.+)$/m', $data, $matches) !== false) {
            return isset($matches[1]) ? trim($matches[1]) : false;
        }

        return false;
    }
}

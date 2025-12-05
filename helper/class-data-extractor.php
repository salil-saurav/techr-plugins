<?php

/**
 * Read XLSX file without external libraries
 * XLSX files are ZIP archives containing XML files
 */

namespace TechrOption;

use ZipArchive;
use WP_Error;

class Simple_XLSX_Reader
{

   private $file_path;
   private $temp_dir;

   public function __construct($file_path)
   {
      $this->file_path = $file_path;
      $this->temp_dir = sys_get_temp_dir() . '/xlsx_' . uniqid();
   }

   /**
    * Extract and read XLSX data
    * Returns array of rows and columns
    */
   public function get_data()
   {
      if (!file_exists($this->file_path)) {
         return new WP_Error('file_not_found', 'XLSX file not found');
      }

      // Extract ZIP
      $zip = new ZipArchive();
      if ($zip->open($this->file_path) !== TRUE) {
         return new WP_Error('zip_error', 'Cannot open XLSX file');
      }

      mkdir($this->temp_dir);
      $zip->extractTo($this->temp_dir);
      $zip->close();

      // Read shared strings (text values are stored here)
      $strings = $this->read_shared_strings();

      // Read worksheet data
      $data = $this->read_worksheet($strings);

      // Cleanup
      $this->cleanup();

      return $data;
   }

   /**
    * Read shared strings XML
    */
   private function read_shared_strings()
   {
      $strings = array();
      $file = $this->temp_dir . '/xl/sharedStrings.xml';

      if (!file_exists($file)) {
         return $strings;
      }

      $xml = simplexml_load_file($file);
      if ($xml === false) {
         return $strings;
      }

      foreach ($xml->si as $item) {
         $strings[] = (string)$item->t;
      }

      return $strings;
   }

   /**
    * Read worksheet data
    */
   private function read_worksheet($strings)
   {
      $data = array();
      $file = $this->temp_dir . '/xl/worksheets/sheet1.xml';

      if (!file_exists($file)) {
         return $data;
      }

      $xml = simplexml_load_file($file);
      if ($xml === false) {
         return $data;
      }

      foreach ($xml->sheetData->row as $row) {
         $row_data = array();
         $current_col_index = 0; // Track which column we are currently expecting

         foreach ($row->c as $cell) {

            // --- FIX STARTS HERE ---

            // Get the cell coordinate (e.g., "A1", "B5", "AA10")
            $cell_coordinate = (string)$cell['r'];

            // Extract the Column Letter (Remove numbers)
            $column_letter = preg_replace('/[0-9]+/', '', $cell_coordinate);

            // Convert Letter to Index (A=0, B=1, etc.)
            $target_col_index = $this->get_column_index($column_letter);

            // Fill empty cells if we skipped any columns
            while ($current_col_index < $target_col_index) {
               $row_data[] = '';
               $current_col_index++;
            }

            // --- FIX ENDS HERE ---

            $value = '';
            $cell_type = (string)$cell['t'];

            // Type 's' means string (reference to sharedStrings)
            if ($cell_type == 's') {
               $index = (int)$cell->v;
               $value = isset($strings[$index]) ? $strings[$index] : '';
            }
            // Inline string
            elseif ($cell_type == 'inlineStr') {
               $value = (string)$cell->is->t;
            }
            // Number or other types
            else {
               $value = (string)$cell->v;
            }

            $row_data[] = $value;
            $current_col_index++; // Increment expectation for next loop
         }

         $data[] = $row_data;
      }

      return $data;
   }

   /**
    * Helper: Convert Column Letter to Index
    * A => 0, Z => 25, AA => 26
    */
   private function get_column_index($col_letter)
   {
      $col_letter = strtoupper($col_letter);
      $length = strlen($col_letter);
      $index = 0;

      for ($i = 0; $i < $length; $i++) {
         $index = $index * 26 + (ord($col_letter[$i]) - 64);
      }

      return $index - 1; // Zero-based index
   }

   /**
    * Clean up temporary files
    */
   private function cleanup()
   {
      if (is_dir($this->temp_dir)) {
         $this->delete_directory($this->temp_dir);
      }
   }

   /**
    * Recursively delete directory
    */
   private function delete_directory($dir)
   {
      if (!is_dir($dir)) {
         return;
      }

      $files = array_diff(scandir($dir), array('.', '..'));
      foreach ($files as $file) {
         $path = $dir . '/' . $file;
         is_dir($path) ? $this->delete_directory($path) : unlink($path);
      }
      rmdir($dir);
   }
}

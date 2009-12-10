<?php
/*
 * @version $Id: compute_dictionnary.php 9588 2009-12-08 20:57:14Z moyo $
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2009 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Walid Nouh
// Purpose of file:
// ----------------------------------------------------------------------
ini_set("memory_limit","-1");
ini_set("max_execution_time", "0");

echo "Checking all table\n";

define('GLPI_ROOT', '..');
include (GLPI_ROOT . "/inc/includes.php");

$result = $DB->list_tables();

for ($i=0 ; $line = $DB->fetch_array($result) ; $i++) {
   $table = $line[0];
   $type = getItemTypeForTable($table);

   if (class_exists($type)) {
      //echo "+  $table > $type : Ok\n";

      $item = new $type();
      if (!isset($item->table)) {
         echo "** $table > $type > no $type->table ** \n";

      } else  if ($table != $item->table) {
         echo "** $table > $type > ".$item->table." incoherent $type->table ** \n";
      }
      $table2 = getTableForItemType($type);
      if ($table != $table2) {
         echo "** $table > $type > ".$table2." incoherent getTableForItemType() ** \n";
      }

   } else {
      echo "** $table > ERROR $type class doesn't exists **\n";
   }
}
echo "End of $i tables analysed\n";

?>

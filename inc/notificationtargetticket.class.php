<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2010 by the INDEPNET Development Team.

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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

// Class NotificationTarget
class NotificationTargetTicket extends NotificationTarget {

   var $private_profiles = array();

   function getSubjectPrefix() {
      return sprintf("[GLPI #%07d] ", $this->obj->getField('id'));
   }


   function getSpecificTargets($data,$options) {

   //Look for all targets whose type is Notification::ITEM_USER
   switch ($data['type']) {
      case Notification::USER_TYPE :

         switch ($data['items_id']) {
            case Notification::TICKET_ASSIGN_TECH :
               $this->getTicketAssignTechnicianAddress();
               break;

            //Send to the group in charge of the ticket supervisor
            case Notification::TICKET_SUPERVISOR_ASSIGN_GROUP :
               $this->getGroupSupervisorAddress(true);
               break;

            //Send to the user who's got the issue
            case Notification::TICKET_RECIPIENT :
               $this->getRecipientAddress();
               break;

            //Send to the supervisor of the requester's group
            case Notification::TICKET_SUPERVISOR_REQUESTER_GROUP :
               $this->getGroupSupervisorAddress(false);
               break;

            //Send to the technician previously in charge of the ticket (before reassignation)
            case Notification::TICKET_OLD_TECH_IN_CHARGE :
               $this->getTicketOldAssignTechnicianAddress();
               break;

            //Assign to a supplier
            case Notification::TICKET_SUPPLIER :
               $this->getTicketSupplierAddress();
               break;

            case Notification::TICKET_REQUESTER_GROUP :
               $this->getRequestGroupAddresses();
               break;

            case Notification::TICKET_ASSIGN_GROUP :
               $this->getAssignGroupAddresses();
               break;
            //Send to the ticket validation approver
            case Notification::TICKET_VALIDATION_APPROVER :
               $this->getTicketValidationApproverAddress($options);
               break;
            //Send to the ticket validation requester
            case Notification::TICKET_VALIDATION_REQUESTER :
               $this->getTicketValidationRequesterAddress($options);
               break;
            //Send to the ticket followup author
            case Notification::TICKET_FOLLOWUP_AUTHOR :
               $this->getTicketFollowupAuthor($options);
               break;
            //Send to the ticket followup author
            case Notification::TICKET_TASK_AUTHOR :
               $this->getTicketTaskAuthor($options);
               break;
            //Send to the ticket followup author
            case Notification::TICKET_TASK_ASSIGN_TECH :
               $this->getTicketTaskAssignUser($options);
               break;
         }
      }
   }


   function addAdditionnalInfosForTarget() {
      global $DB;

      $query = "SELECT `id`
                FROM `glpi_profiles`
                WHERE `glpi_profiles`.`show_full_ticket` = '1'";

      foreach ($DB->request($query) as $data) {
         $this->private_profiles[$data['id']] = $data['id'];
      }
   }


   /**
    * Get item associated with the object on which the event was raised
    * @return the object associated with the itemtype
    */
   function getObjectItem() {

      if ($this->obj) {
         $itemtype = $this->obj->getField('itemtype');
         if ($itemtype != '') {
            $item = new  $itemtype ();
            $item->getFromDB($this->obj->getField('items_id'));
            $this->target_object = $item;
         }
      }
   }


   function getRequestGroupAddresses() {

      if ($this->obj->fields['groups_id']) {
         $this->getUsersAddressesByGroup($this->obj->fields['groups_id']);
      }
   }


   function getAssignGroupAddresses() {

      if ($this->obj->fields['groups_id_assign']) {
         $this->getUsersAddressesByGroup($this->obj->fields['groups_id_assign']);
      }
   }


   function getTicketAssignTechnicianAddress () {
      return $this->getUserByField ("users_id_assign");
   }


   function getTicketOldAssignTechnicianAddress () {
      return $this->getUserByField ("_old_assign");
   }


   //Get receipient
   function getRecipientAddress() {
      return $this->getUserByField ("users_id_recipient");
  }


   /**
    * Get supplier related to the ticket
    */
   function getTicketSupplierAddress($sendprivate=false) {
      global $DB;

      if (!$sendprivate
          && isset($this->obj->fields["suppliers_id_assign"])
          && $this->obj->fields["suppliers_id_assign"]>0) {

         $query = "SELECT DISTINCT `glpi_suppliers`.`email` AS email
                   FROM `glpi_suppliers`
                   WHERE `glpi_suppliers`.`id` = '".$this->obj->fields["suppliers_id_assign"]."'";

         foreach ($DB->request($query) as $data) {
            $this->addToAddressesList($data);
         }
      }
   }

   /**
    * Get approuver related to the ticket validation
    */
   function getTicketValidationApproverAddress($options=array()) {
      global $DB;

      if (isset($options['validation_id'])) {
         $query = "SELECT DISTINCT `glpi_users`.`email` AS email, `glpi_users`.`language` AS language
                  FROM `glpi_ticketvalidations`
                  LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `glpi_ticketvalidations`.`users_id_validate`)
                  WHERE `glpi_ticketvalidations`.`id` = '".$options['validation_id']."'";

         foreach ($DB->request($query) as $data) {
            $this->addToAddressesList($data);
         }
      }
   }

   /**
    * Get requester related to the ticket validation
    */
   function getTicketValidationRequesterAddress($options=array()) {
      global $DB;

      if (isset($options['validation_id'])) {
         $query = "SELECT DISTINCT `glpi_users`.`email` AS email, `glpi_users`.`language` AS language
                  FROM `glpi_ticketvalidations`
                  LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `glpi_ticketvalidations`.`users_id`)
                  WHERE `glpi_ticketvalidations`.`id` = '".$options['validation_id']."'";

         foreach ($DB->request($query) as $data) {
            $this->addToAddressesList($data);
         }
      }
   }

   /**
    * Get author related to the followup
    */
   function getTicketFollowupAuthor($options=array()) {
      global $DB;

      if (isset($options['followup_id'])) {
         $query = "SELECT DISTINCT `glpi_users`.`email` AS email, `glpi_users`.`language` AS language
                  FROM `glpi_ticketfollowups`
                  LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `glpi_ticketfollowups`.`users_id`)
                  WHERE `glpi_ticketfollowups`.`id` = '".$options['followup_id']."'";

         foreach ($DB->request($query) as $data) {
            $this->addToAddressesList($data);
         }
      }
   }

   /**
    * Get author related to the followup
    */
   function getTicketTaskAuthor($options=array()) {
      global $DB;

      if (isset($options['task_id'])) {
         $query = "SELECT DISTINCT `glpi_users`.`email` AS email, `glpi_users`.`language` AS language
                  FROM `glpi_tickettasks`
                  LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `glpi_tickettasks`.`users_id`)
                  WHERE `glpi_tickettasks`.`id` = '".$options['task_id']."'";

         foreach ($DB->request($query) as $data) {
            $this->addToAddressesList($data);
         }
      }
   }

   /**
    * Get author related to the followup
    */
   function getTicketTaskAssignUser($options=array()) {
      global $DB;

      if (isset($options['task_id'])) {
         $query = "SELECT DISTINCT `glpi_users`.`email` AS email, `glpi_users`.`language` AS language
                  FROM `glpi_tickettasks`
                  LEFT JOIN `glpi_ticketplannings`
                     ON (`glpi_ticketplannings`.`tickettasks_id` = `glpi_tickettasks`.`id`)
                  LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `glpi_ticketplannings`.`users_id`)
                  WHERE `glpi_tickettasks`.`id` = '".$options['task_id']."'";

         foreach ($DB->request($query) as $data) {
            $this->addToAddressesList($data);
         }
      }
   }
   

   function addAdditionnalUserInfo($data) {
      global $DB;

      if (!isset($data['id'])) {
         return 1;
      } else {
         $query = "SELECT count(*) AS cpt
                   FROM `glpi_profiles_users`
                   WHERE `users_id`='".$data['id']."'
                         AND profiles_id IN (".implode(',',$this->private_profiles).")";

         $result = $DB->query($query);
         if ($DB->result($result,0,'cpt')) {
            return 1;
         }
         return 0;
      }
   }


   /**
    * Get supervisor of a group (works for request group or assigned group)
    */
   function getGroupSupervisorAddress ($assign=true) {
      global $DB;

      $group_field = ($assign?"groups_id_assign":"groups_id");

      if (isset($this->obj->fields[$group_field]) && $this->obj->fields[$group_field]>0) {

         $query = $this->getDistinctUserSql()."
                  FROM `glpi_groups`
                  LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `glpi_groups`.`users_id`)".
                  $this->getJoinProfileSql()."
                  WHERE `glpi_groups`.`id` = '".$this->obj->fields[$group_field]."'";

         foreach ($DB->request($query) as $data) {
            $this->addToAddressesList($data);
         }
      }
   }


   /**
    *Get events related to tickets
    */
   function getEvents() {
      global $LANG;

      $events = array ('new'         => $LANG['mailing'][9],
                    'update'       => $LANG['mailing'][30],
                    'solved'       => $LANG['mailing'][123],
                    'validation'   => $LANG['validation'][26],
                    'add_followup' => $LANG['mailing'][10],
                    'update_followup' => $LANG['mailing'][134],
                    'delete_followup' => $LANG['mailing'][135],
                    'add_task'     => $LANG['job'][49],
                    'update_task'     => $LANG['job'][52],
                    'delete_task'     => $LANG['job'][53],
                    'closed'       => $LANG['mailing'][127],
                    'delete'       => $LANG['mailing'][129]);
      asort($events);
      return $events;
   }


   /**
    * Get additionnals targets for Tickets
    */
   function getAdditionalTargets($event='') {
      global $LANG;

      $this->addTarget(Notification::TICKET_OLD_TECH_IN_CHARGE,$LANG['setup'][236]);
      $this->addTarget(Notification::TICKET_RECIPIENT,$LANG['job'][3]);
      $this->addTarget(Notification::TICKET_SUPPLIER,$LANG['financial'][26]);
      $this->addTarget(Notification::TICKET_SUPERVISOR_ASSIGN_GROUP,$LANG['common'][64]." ".
                                                                    $LANG['setup'][248]);
      $this->addTarget(Notification::TICKET_SUPERVISOR_REQUESTER_GROUP,$LANG['common'][64]." ".
                                                                       $LANG['setup'][249]);
      $this->addTarget(Notification::ITEM_TECH_IN_CHARGE,$LANG['common'][10]);
      $this->addTarget(Notification::TICKET_ASSIGN_TECH,$LANG['setup'][239]);
      $this->addTarget(Notification::TICKET_REQUESTER_GROUP,$LANG['setup'][249]);
      $this->addTarget(Notification::AUTHOR,$LANG['job'][4]);
      $this->addTarget(Notification::ITEM_USER,$LANG['common'][34]);
      $this->addTarget(Notification::TICKET_ASSIGN_GROUP,$LANG['setup'][248]);
      if ($event=='validation') {
         $this->addTarget(Notification::TICKET_VALIDATION_APPROVER,$LANG['validation'][0].' - '.$LANG['validation'][21]);
         $this->addTarget(Notification::TICKET_VALIDATION_REQUESTER,$LANG['validation'][0].' - '.$LANG['validation'][18]);
      }
      if ($event=='update_task' || $event=='add_task' || $event=='delete_task') {
         $this->addTarget(Notification::TICKET_TASK_ASSIGN_TECH,$LANG['job'][7]." - ".$LANG['job'][6]);
         $this->addTarget(Notification::TICKET_TASK_AUTHOR,$LANG['job'][7]." - ".$LANG['common'][37]);
      }
      if ($event=='update_followup' || $event=='add_followup' || $event=='delete_followup') {
         $this->addTarget(Notification::TICKET_FOLLOWUP_AUTHOR,$LANG['job'][9]." - ".$LANG['common'][37]);
      }
   }


   function getJoinSql() {

      if ($this->isPrivate()) {
         return " INNER JOIN `glpi_profiles_users`
                     ON (`glpi_profiles_users`.`users_id` = `glpi_users`.`id`".
                         getEntitiesRestrictRequest("AND","glpi_profiles_users","entities_id",
                                                    $this->obj->fields['entities_id'],true).")
                  INNER JOIN `glpi_profiles`
                     ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`
                         AND `glpi_profiles`.`interface` = 'central'
                         AND `glpi_profiles`.`show_full_ticket` = '1') ";
      }
      return "";
   }


   function getJoinProfileSql() {

      if ($this->isPrivate()) {
         return " INNER JOIN `glpi_profiles`
                     ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`
                         AND `glpi_profiles`.`interface` = 'central'
                         AND `glpi_profiles`.`show_full_ticket` = '1')";
      }
      return "";
   }


   function isPrivate() {

      if (isset($this->options['sendprivate']) && $this->options['sendprivate'] == 1) {
         return true;
      }
      return false;
   }


   /**
    * Get all data needed for template processing
    */
   function getDatasForTemplate($event, $options=array()) {
      global $LANG, $CFG_GLPI;

      //----------- Ticket infos -------------- //

      $fields = array ('ticket.title'        => 'name',
                       'ticket.content'      => 'content',
                       'ticket.costfixed'    => 'cost_fixed',
                       'ticket.costmaterial' => 'cost_material',
                       'ticket.useremail'    => 'user_email');

      foreach ($fields as $tag => $table_field) {
         $this->datas['##'.$tag.'##'] = $this->obj->getField($table_field);
      }
      $this->datas['##ticket.id##']  = sprintf("%07d",$this->obj->getField("id"));
      $this->datas['##ticket.url##'] = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=ticket_".
                                                 $this->obj->getField("id"));
      $this->datas['##ticket.urlapprove##'] = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=ticket_".
                                                 $this->obj->getField("id")."_4");

      $this->datas['##ticket.entity##'] = Dropdown::getDropdownName('glpi_entities',
                                                               $this->obj->getField('entities_id'));
      $events = $this->getAllEvents();
      $this->datas['##ticket.action##']      = $events[$event];
      $this->datas['##ticket.storestatus##']      = $this->obj->getField('status');
      $this->datas['##ticket.status##']      = Ticket::getStatus($this->obj->getField('status'));
      $this->datas['##ticket.requesttype##'] = Dropdown::getDropdownName('glpi_requesttypes',
                                                            $this->obj->getField('requesttypes_id'));

      $this->datas['##ticket.urgency##']  = Ticket::getUrgencyName($this->obj->getField('urgency'));
      $this->datas['##ticket.impact##']   = Ticket::getImpactName($this->obj->getField('impact'));
      $this->datas['##ticket.priority##'] = Ticket::getPriorityName($this->obj->getField('priority'));
      $this->datas['##ticket.time##']     = convDateTime($this->obj->getField('realtime'));
      $this->datas['##ticket.costtime##'] = $this->obj->getField('cost_time');

     $this->datas['##ticket.creationdate##'] = convDateTime($this->obj->getField('date'));
     $this->datas['##ticket.closedate##']    = convDateTime($this->obj->getField('closedate'));

     $this->datas['##lang.ticket.days##'] = $LANG['stats'][31];

     $entitydata = new EntityData;

     if ($entitydata->getFromDB($this->obj->getField('entities_id'))
            && $entitydata->getField('autoclose_delay') > 0) {
         $this->datas['##ticket.autoclose##'] = $entitydata->fields['autoclose_delay'];
         $this->datas['##lang.ticket.autoclosewarning##'] = $LANG['job'][54]." ".
                              $entitydata->fields['autoclose_delay']." ".$LANG['stats'][31];
         
     } else {
        $this->datas['##ticket.autoclose##'] = $LANG['setup'][307];
     }
     $this->datas['##lang.ticket.autoclose##'] = $LANG['entity'][18];

      if ($this->obj->getField('ticketcategories_id')) {
         $this->datas['##ticket.category##'] = Dropdown::getDropdownName('glpi_ticketcategories',
                                                      $this->obj->getField('ticketcategories_id'));
      } else {
         $this->datas['##ticket.category##'] = '';
      }

      if ($this->obj->getField('users_id')) {
         $user = new User;
         $user->getFromDB($this->obj->getField('users_id'));
         $this->datas['##ticket.author##']      = $user->getField('id');
         $this->datas['##ticket.author.name##'] = $user->getField('name');
         if ($user->getField('locations_id')) {
            $this->datas['##ticket.author.location##'] = Dropdown::getDropdownName('glpi_locations',
                                                                  $user->getField('locations_id'));
         } else {
            $this->datas['##ticket.author.location##'] = '';
         }
         $this->datas['##ticket.author.phone##']  = $user->getField('phone');
         $this->datas['##ticket.author.phone2##'] = $user->getField('phone2');
      }

      if ($this->obj->getField('users_id_recipient')) {
         $this->datas['##ticket.openbyuser##'] = Dropdown::getDropdownName('glpi_users',
                                                         $this->obj->getField('users_id_recipient'));
      } else {
         $this->datas['##ticket.openbyuser##'] = '';
      }

      if ($this->obj->getField('users_id_assign')) {
         $this->datas['##ticket.assigntouser##'] = Dropdown::getDropdownName('glpi_users',
                                                            $this->obj->getField('users_id_assign'));
      } else {
         $this->datas['##ticket.assigntouser##'] = '';
      }

      if ($this->obj->getField('suppliers_id_assign')) {
         $this->datas['##ticket.assigntosupplier##'] = Dropdown::getDropdownName('glpi_suppliers',
                                                         $this->obj->getField('suppliers_id_assign'));
      } else {
         $this->datas['##ticket.assigntosupplier##'] = '';
      }

      if ($this->obj->getField('groups_id')) {
         $this->datas['##ticket.group##'] = Dropdown::getDropdownName('glpi_groups',
                                                                  $this->obj->getField('groups_id'));
      } else {
         $this->datas['##ticket.group##'] = '';
      }

      if ($this->obj->getField('groups_id_assign')) {
         $this->datas['##ticket.assigntogroup##'] = Dropdown::getDropdownName('glpi_groups',
                                                         $this->obj->getField('groups_id_assign'));
      } else {
         $this->datas['##ticket.group##'] = '';
      }

      //Hardware
      if ($this->target_object != null) {
         $this->datas['##ticket.itemtype##']  = $this->target_object->getTypeName();
         $this->datas['##ticket.item.name##'] = $this->target_object->getField('name');

         if ($this->target_object->isField('serial')) {
            $this->datas['##ticket.item.serial##'] = $this->target_object->getField('serial');
         }
         if ($this->target_object->isField('otherserial')) {
            $this->datas['##ticket.item.otherserial##'] = $this->target_object->getField('otherserial');
         }
         if ($this->target_object->isField('location')) {
            $this->datas['##ticket.item.location##'] = Dropdown::getDropdownName('glpi_locations',
                                                                   $user->getField('locations_id'));
         }
         $modeltable = getSingular($this->getTable())."models";
         $modelfield = getForeignKeyFieldForTable($modeltable);
         if ($this->target_object->isField($modelfield)) {
            $this->datas['##ticket.item.model##'] = $this->target_object->getField($modelfield);
         }

      } else {
         $this->datas['##ticket.itemtype##']         = '';
         $this->datas['##ticket.item.name##']        = '';
         $this->datas['##ticket.item.serial##']      = '';
         $this->datas['##ticket.item.otherserial##'] = '';
         $this->datas['##ticket.item.location##']    = '';
      }

      if ($this->obj->getField('ticketsolutiontypes_id')) {
         $this->datas['##ticket.solution.type##'] = Dropdown::getDropdownName('glpi_ticketsolutiontypes',
                                                      $this->obj->getField('ticketsolutiontypes_id'));
         $this->datas['##ticket.solution.description##'] = $this->obj->getField('solution');
      }

      $restrict = "`tickets_id`='".$this->obj->getField('id')."'";
      if (!isset($options['additionnaloption']) || !$options['additionnaloption']) {
         $restrict .= " AND `is_private` = '0'";
      }
      $restrict .= " ORDER BY `date` DESC";

      //Task infos
      $tasks = getAllDatasFromTable('glpi_tickettasks',$restrict);
      foreach ($tasks as $task) {
         $tmp = array();
         $tmp['##task.isprivate##']   = Dropdown::getYesNo($task['is_private']);
         $tmp['##task.author##']      = Dropdown::getDropdownName('glpi_users', $task['users_id']);
         $tmp['##task.category##']    = Dropdown::getDropdownName('glpi_taskcategories',
                                                                  $task['taskcategories_id']);
         $tmp['##task.date##']        = convDateTime($task['date']);
         $tmp['##task.description##'] = $task['content'];
         $tmp['##task.time##']        = $task['realtime'];
         $this->datas['tasks'][] = $tmp;
      }
      if (!empty($this->datas['tasks'])) {
         $this->datas['##ticket.numberoftasks##'] = count($this->datas['tasks']);
      } else {
         $this->datas['##ticket.numberoftasks##'] = 0;
      }

      //Followup infos
      $followups = getAllDatasFromTable('glpi_ticketfollowups',$restrict);
      foreach ($followups as $followup) {
         $tmp = array();
         $tmp['##followup.isprivate##']   = Dropdown::getYesNo($followup['is_private']);
         $tmp['##followup.author##']      = Dropdown::getDropdownName('glpi_users',
                                                                      $followup['users_id']);
         $tmp['##followup.requesttype##'] = Dropdown::getDropdownName('glpi_requesttypes',
                                                                       $followup['requesttypes_id']);
         $tmp['##followup.date##']        = convDateTime($followup['date']);
         $tmp['##followup.description##'] = $followup['content'];
         $this->datas['followups'][] = $tmp;
      }
      if (isset($this->datas['followups'])) {
         $this->datas['##ticket.numberoffollowups##'] = count($this->datas['followups']);
      } else {
         $this->datas['##ticket.numberoffollowups##'] = 0;
      }

      //Validation infos
      $restrict = "`tickets_id`='".$this->obj->getField('id')."'";
      if (isset($options['validation_id']) && $options['validation_id']) {
         $restrict .= " AND `glpi_ticketvalidations`.`id` = '".$options['validation_id']."'";
      }
      $restrict .= " ORDER BY `submission_date` DESC";
      $validations = getAllDatasFromTable('glpi_ticketvalidations',$restrict);

      foreach ($validations as $validation) {
         $tmp = array();
         $tmp['##lang.validation.submission.title##']
            = $LANG['validation'][27]." (".$LANG['job'][4].
              " ".html_clean(getUserName($validation['users_id'])).")";
         $tmp['##lang.validation.answer.title##']
            = $LANG['validation'][32]." (".$LANG['validation'][21].
              " ".html_clean(getUserName($validation['users_id_validate'])).")";

         $tmp['##validation.url##']
            = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=ticket_".
              $validation['tickets_id']."_7");

         $tmp['##validation.author##']            = html_clean(getUserName($validation['users_id']));
         $tmp['##lang.validation.validationstatus##']
            = $LANG['validation'][28]." : ". TicketValidation::getStatus($validation['status']);

         $tmp['##validation.status##']            = TicketValidation::getStatus($validation['status']);
         $tmp['##validation.storestatus##']       = $validation['status'];
         $tmp['##validation.submissiondate##']    = convDateTime($validation['submission_date']);
         $tmp['##validation.commentsubmission##'] = $validation['comment_submission'];
         $tmp['##validation.validationdate##']    = convDateTime($validation['validation_date']);
         $tmp['##validation.commentvalidation##'] = $validation['comment_validation'];

         $this->datas['validations'][] = $tmp;
      }

      // Use list_limit_max or load the full history ?
      foreach (Log::getHistoryData($this->obj,0,$CFG_GLPI['list_limit_max']) as $data) {
         $tmp = array();
         $tmp['##ticket.log.date##']    = $data['date_mod'];
         $tmp['##ticket.log.user##']    = $data['user_name'];
         $tmp['##ticket.log.field##']   = $data['field'];
         $tmp['##ticket.log.content##'] = $data['change'];
         $this->datas['log'][] = $tmp;
      }

      if (isset($this->datas['log'])) {
         $this->datas['##ticket.numberoflogs##'] = count($this->datas['log']);
      } else {
         $this->datas['##ticket.numberoflogs##'] = 0;
      }

      //Locales
      $labels = array ('##lang.ticket.id##'                    => $LANG['common'][2],
                       '##lang.ticket.title##'                 => $LANG['common'][16],
                       '##lang.ticket.url##'                   => 'URL',
                       '##lang.ticket.entity##'                => $LANG['entity'][0],
                       '##lang.ticket.category##'              => $LANG['common'][36],
                       '##lang.ticket.content##'               => $LANG['joblist'][6],
                       '##lang.ticket.description##'           => $LANG['mailing'][5],
                       '##lang.ticket.status##'                => $LANG['joblist'][0],
                       '##lang.ticket.creationdate##'          => $LANG['reports'][60],
                       '##lang.ticket.closedate##'             => $LANG['reports'][61],
                       '##lang.ticket.requesttype##'           => $LANG['job'][44],
                       '##lang.ticket.author##'        => $LANG['common'][2].' '.$LANG['job'][4],
                       '##lang.ticket.author.name##'           => $LANG['job'][4],
                       '##lang.ticket.author.location##'       => $LANG['common'][15],
                       '##lang.ticket.author.phone##'          => $LANG['help'][35],
                       '##lang.ticket.openbyuser##'            => $LANG['job'][3],
                       '##lang.ticket.group##'                 => $LANG['common'][35],
                       '##lang.ticket.assigntouser##'  => $LANG['job'][5]." - ".$LANG['job'][6],
                       '##lang.ticket.assigntogroup##' => $LANG['job'][5]." - ".$LANG['common'][35],
                       '##lang.ticket.assigntosupplier##'      => $LANG['job'][5].
                                                                  " - ".$LANG['financial'][26],
                       '##lang.ticket.itemtype##'              => $LANG['reports'][12],
                       '##lang.ticket.item.name##'             => $LANG['financial'][104],
                       '##lang.ticket.item.serial##'           => $LANG['common'][19],
                       '##lang.ticket.item.otherserial##'      => $LANG['common'][20],
                       '##lang.ticket.item.location##'         => $LANG['common'][15],
                       '##lang.ticket.item.model##'            => $LANG['common'][22],
                       '##lang.ticket.urgency##'               => $LANG['joblist'][29],
                       '##lang.ticket.impact##'                => $LANG['joblist'][30],
                       '##lang.ticket.priority##'              => $LANG['joblist'][2],
                       '##lang.ticket.time##'                  => $LANG['job'][20],
                       '##lang.ticket.costtime##'              => $LANG['job'][40],
                       '##lang.ticket.costfixed##'             => $LANG['job'][41],
                       '##lang.ticket.costmaterial##'          => $LANG['job'][42],
                       '##lang.ticket.solution.type##'         => $LANG['job'][48],
                       '##lang.ticket.solution.comment##'      => $LANG['common'][25],
                       '##lang.ticket.solution.name##'         => $LANG['jobresolution'][1],
                       '##lang.task.author##'                  => $LANG['job'][4],
                       '##lang.task.isprivate##'               => $LANG['common'][77],
                       '##lang.task.date##'                    => $LANG['reports'][60],
                       '##lang.task.description##'             => $LANG['joblist'][6],
                       '##lang.task.category##'                => $LANG['common'][36],
                       '##lang.task.time##'                    => $LANG['job'][20],
                       '##lang.followup.date##'                => $LANG['reports'][60],
                       '##lang.followup.isprivate##'           => $LANG['common'][77],
                       '##lang.followup.author##'              => $LANG['job'][4],
                       '##lang.followup.description##'         => $LANG['joblist'][6],
                       '##lang.followup.requesttype##'         => $LANG['job'][44],
                       '##lang.ticket.numberoffollowups##'     => $LANG['mailing'][4],
                       '##lang.ticket.numberoftasks##'         => $LANG['mailing'][122],
                       '##lang.ticket.nocategoryassigned##'    => $LANG['mailing'][100],
                       '##lang.validation.author##'            => $LANG['job'][4],
                       '##lang.validation.status##'            => $LANG['joblist'][0],
                       '##lang.validation.submissiondate##'    => $LANG['validation'][3],
                       '##lang.validation.commentsubmission##' => $LANG['validation'][5],
                       '##lang.validation.validationdate##'    => $LANG['validation'][4],
                       '##lang.validation.commentvalidation##' => $LANG['validation'][6]);

      foreach ($labels as $tag => $label) {
         $this->datas[$tag] = $label;
      }
   }


   static function isAuthorMailingActivatedForHelpdesk() {
      global $DB,$CFG_GLPI;

      if ($CFG_GLPI['use_mailing']) {
         $query = "SELECT COUNT(`glpi_notifications`.`id`)
                   FROM `glpi_notifications` INNER JOIN `glpi_notificationtargets`
                     ON (`glpi_notifications`.`id` = `glpi_notificationtargets`.`notifications_id` )
                   WHERE `glpi_notifications`.`itemtype` = 'Ticket'
                         AND `glpi_notifications`.`mode` = 'mail'
                         AND `glpi_notificationtargets`.`type` = '".Notification::USER_TYPE."'
                         AND `glpi_notificationtargets`.`items_id` = '".Notification::AUTHOR."'";

         if ($result = $DB->query($query)) {
            if ($DB->result($result,0,0) >0) {
               return true;
            }
         }
      }
      return false;
   }

}
?>
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include('./include/auth.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/data_query.php');

$tree_actions = array(
	1 => __x('dropdown action', 'Delete'),
	2 => __x('dropdown action', 'Publish'),
	3 => __x('dropdown action', 'Un Publish')
);

/* set default action */
set_default_action();

if (get_request_var('action') != '') {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'tree_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => ''
			),
		'leaf_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => ''
			),
		'graph_tree_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => ''
			),
		'parent_item_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => ''
			),
		'parent' => array(
			'filter' => FILTER_VALIDATE_REGEXP, 
			'options' => array('options' => array('regexp' => '/([_\-a-z:0-9#]+)/')),
			'pageset' => true,
			'default' => ''
			),
		'position' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => ''
			),
		'nodeid' => array(
			'filter' => FILTER_VALIDATE_REGEXP, 
			'options' => array('options' => array('regexp' => '/([_\-a-z:0-9#]+)/')),
			'pageset' => true,
			'default' => ''
			),
		'id' => array(
			'filter' => FILTER_VALIDATE_REGEXP, 
			'options' => array('options' => array('regexp' => '/([_\-a-z:0-9#]+)/')),
			'pageset' => true,
			'default' => ''
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => '', 
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters);
	/* ================= input validation ================= */
}

switch (get_request_var('action')) {
	case 'save':
		form_save();
		break;
	case 'actions':
        form_actions();
        break;
	case 'edit':
		top_header();
		tree_edit();
		bottom_footer();
		break;
	case 'hosts':
		display_hosts();
		break;
	case 'graphs':
		display_graphs();
		break;
	case 'tree_up':
		tree_up();
		break;
	case 'tree_down':
		tree_down();
		break;
	case 'lock':
		api_tree_lock(get_request_var('id'), $_SESSION['sess_user_id']);
		break;
	case 'unlock':
		api_tree_unlock(get_request_var('id'), $_SESSION['sess_user_id']);
		break;
	case 'copy_node':
		api_tree_copy_node(get_request_var('tree_id'), get_request_var('id'), get_request_var('parent'), get_request_var('position'));
		break;
	case 'create_node':
		api_tree_create_node(get_request_var('tree_id'), get_request_var('id'), get_request_var('position'), get_nfilter_request_var('text'));
		break;
	case 'delete_node':
		api_tree_delete_node(get_request_var('tree_id'), get_request_var('id'));
		break;
	case 'move_node':
		api_tree_move_node(get_request_var('tree_id'), get_request_var('id'), get_request_var('parent'), get_request_var('position'));
		break;
	case 'rename_node':
		api_tree_rename_node(get_request_var('tree_id'), get_request_var('id'), get_nfilter_request_var('text'));
		break;
	case 'get_node':
		api_tree_get_node(get_request_var('tree_id'), get_request_var('id'));
		break;
	case 'get_host_sort':
		get_host_sort_type();
		break;
	case 'set_host_sort':
		set_host_sort_type();
		break;
	case 'get_branch_sort':
		get_branch_sort_type();
		break;
	case 'set_branch_sort':
		set_branch_sort_type();
		break;
	default:
		top_header();
		tree();
		bottom_footer();
		break;
}

function tree_down() {
	$tree_id = get_filter_request_var('id');
	$seq     = db_fetch_cell_prepared('SELECT sequence FROM graph_tree WHERE id = ?', array($tree_id));
	$new_seq = $seq + 1;

	/* update the old tree first */
	db_execute_prepared('UPDATE graph_tree SET sequence = ? WHERE sequence = ?', array($seq, $new_seq));
	/* update the tree in question */
	db_execute_prepared('UPDATE graph_tree SET sequence = ? WHERE id = ?', array($new_seq, $tree_id));

	header('Location: tree.php?header=false');
	exit;
}

function tree_up() {
	$tree_id = get_filter_request_var('id');
	$seq     = db_fetch_cell_prepared('SELECT sequence FROM graph_tree WHERE id = ?', array($tree_id));
	$new_seq = $seq - 1;

	/* update the old tree first */
	db_execute_prepared('UPDATE graph_tree SET sequence = ? WHERE sequence = ?', array($seq, $new_seq));
	/* update the tree in question */
	db_execute_prepared('UPDATE graph_tree SET sequence = ? WHERE id = ?', array($new_seq, $tree_id));

	header('Location: tree.php?header=false');
	exit;
}

function get_host_sort_type() {
	if (isset_request_var('nodeid')) {
		$ndata = explode('_', get_request_var('nodeid'));
		if (sizeof($ndata)) {
			foreach($ndata as $n) {
				$parts = explode(':', $n);

				if (isset($parts[0]) && $parts[0] == 'tbranch') {
					$branch = $parts[1];
					input_validate_input_number($branch);
					$sort_type = db_fetch_cell_prepared('SELECT host_grouping_type FROM graph_tree_items WHERE id = ?', array($branch));
					if ($sort_type == HOST_GROUPING_GRAPH_TEMPLATE) {
						print 'hsgt';
					}else{
						print 'hsdq';
					}
				}
			}
		}
	}else{
		return '';
	}
}

function set_host_sort_type() {
	$type   = '';
	$branch = '';

	/* clean up type string */
	if (isset_request_var('type')) {
		set_request_var('type', sanitize_search_string(get_request_var('type')));
	}

	if (isset_request_var('nodeid')) {
		$ndata = explode('_', get_request_var('nodeid'));
		if (sizeof($ndata)) {
			foreach($ndata as $n) {
				$parts = explode(':', $n);

				if (isset($parts[0]) && $parts[0] == 'tbranch') {
					$branch = $parts[1];
					input_validate_input_number($branch);

					if (get_request_var('type') == 'hsgt') {
						$type = HOST_GROUPING_GRAPH_TEMPLATE;
					}else{
						$type = HOST_GROUPING_DATA_QUERY_INDEX;
					}

					db_execute_prepared('UPDATE graph_tree_items SET host_grouping_type = ? WHERE id = ?', array($type, $branch));
					break;
				}
			}
		}
	}

	return;
}

function get_branch_sort_type() {
	if (isset_request_var('nodeid')) {
		$ndata = explode('_', get_request_var('nodeid'));
		if (sizeof($ndata)) {
		foreach($ndata as $n) {
			$parts = explode(':', $n);

			if (isset($parts[0]) && $parts[0] == 'tbranch') {
				$branch = $parts[1];
				input_validate_input_number($branch);
				$sort_type = db_fetch_cell_prepared('SELECT sort_children_type FROM graph_tree_items WHERE id = ?', array($branch));
				switch($sort_type) {
				case TREE_ORDERING_INHERIT:
					print __x('ordering of tree items', 'inherit');
					break;
				case TREE_ORDERING_NONE:
					print __x('ordering of tree items', 'manual');
					break;
				case TREE_ORDERING_ALPHABETIC:
					print __x('ordering of tree items', 'alpha');
					break;
				case TREE_ORDERING_NATURAL:
					print __x('ordering of tree items', 'natural');
					break;
				case TREE_ORDERING_NUMERIC:
					print __x('ordering of tree items', 'numeric');
					break;
				default:
					print '';
					break;
				}
				break;
			}
		}
		}
	}else{
		print '';
	}
}

function set_branch_sort_type() {
	$type   = '';
	$branch = '';

	/* clean up type string */
	if (isset_request_var('type')) {
		set_request_var('type', sanitize_search_string(get_request_var('type')));
	}

	if (isset_request_var('nodeid')) {
		$ndata = explode('_', get_request_var('nodeid'));
		if (sizeof($ndata)) {
		foreach($ndata as $n) {
			$parts = explode(':', $n);

			if (isset($parts[0]) && $parts[0] == 'tbranch') {
				$branch = $parts[1];
				input_validate_input_number($branch);

				switch(get_request_var('type')) {
				case 'inherit':
					$type = TREE_ORDERING_INHERIT;
					break;
				case 'manual':
					$type = TREE_ORDERING_NONE;
					break;
				case 'alpha':
					$type = TREE_ORDERING_ALPHABETIC;
					break;
				case 'natural':
					$type = TREE_ORDERING_NATURAL;
					break;
				case 'numeric':
					$type = TREE_ORDERING_NUMERIC;
					break;
				default:
					break;
				}

				if (is_numeric($type) && is_numeric($branch)) {
					db_execute_prepared('UPDATE graph_tree_items SET sort_children_type = ? WHERE id = ?', array($type, $branch));
				}

				$first_child = db_fetch_row_prepared('SELECT id, graph_tree_id FROM graph_tree_items WHERE parent = ? ORDER BY position LIMIT 1', array($branch));
				if (!empty($first_child)) {
					api_tree_sort_branch($first_child['id'], $first_child['graph_tree_id']);
				}

				break;
			}
		}
		}
	}
}

/* --------------------------
    The Save Function
   -------------------------- */
function form_save() {
	/* clear graph tree cache on save - affects current user only, other users should see changes in <5 minutes */
	if (isset($_SESSION['dhtml_tree'])) {
		unset($_SESSION['dhtml_tree']);
	}

	if (isset_request_var('save_component_tree')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		if (get_filter_request_var('id') > 0) {
			$prev_order = db_fetch_cell_prepared('SELECT sort_type FROM graph_tree WHERE id = ?', array(get_request_var('id')));
		}else{
			$prev_order = 1;
		}

		$save['id']            = get_request_var('id');
		$save['name']          = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['sort_type']     = form_input_validate(get_nfilter_request_var('sort_type'), 'sort_type', '', true, 3);
		$save['last_modified'] = date('Y-m-d H:i:s', time());
		$save['enabled']       = get_nfilter_request_var('enabled') == 'true' ? 'on':'-';
		$save['modified_by']   = $_SESSION['sess_user_id'];
		if (empty($save['id'])) {
			$save['user_id'] = $_SESSION['sess_user_id'];
		}

		if (!is_error_message()) {
			$tree_id = sql_save($save, 'graph_tree');

			if ($tree_id) {
				raise_message(1);

				/* sort the tree using the algorithm chosen by the user */
				if ($save['sort_type'] != $prev_order) {
					if ($save['sort_type'] != TREE_ORDERING_NONE) {
						sort_recursive(0, $tree_id);
					}
				}
			}else{
				raise_message(2);
			}
		}

		header("Location: tree.php?header=false&action=edit&id=$tree_id");
		exit;
	}
}

function sort_recursive($branch, $tree_id) {
	$leaves = db_fetch_assoc_prepared('SELECT * FROM graph_tree_items WHERE graph_tree_id = ? AND parent = ? AND local_graph_id = 0 AND host_id = 0', array($tree_id, $branch));

	if (sizeof($leaves)) {
	foreach($leaves as $leaf) {
		if ($leaf['sort_children_type'] == TREE_ORDERING_INHERIT) {
			$first_child = db_fetch_cell_prepared('SELECT id FROM graph_tree_items WHERE parent = ?', array($leaf['id']));

			if (!empty($first_child)) {
				api_tree_sort_branch($first_child, $tree_id);

				if (leaves_exist($leaf['id'], $tree_id)) {
					sort_recursive($first_child, $tree_id);
				}
			}
		}
	}
	}
}

function leaves_exist($parent, $tree_id) {
	return db_fetch_assoc_prepared('SELECT COUNT(*) 
		FROM graph_tree_items 
		WHERE graph_tree_id = ? 
		AND parent = ? 
		AND local_graph_id = 0 
		AND host_id = 0', array($tree_id, $parent));
}

/* -----------------------
    Tree Item Functions
   ----------------------- */
function form_actions() {
	global $tree_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM graph_tree WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM graph_tree_items WHERE ' . array_to_sql_or($selected_items, 'graph_tree_id'));
			}elseif (get_nfilter_request_var('drp_action') == '2') { /* publish */
				db_execute("UPDATE graph_tree 
					SET enabled='on',
					last_modified=NOW(),
					modified_by=" . $_SESSION['sess_user_id'] . '
					WHERE ' . array_to_sql_or($selected_items, 'id'));
			}elseif (get_nfilter_request_var('drp_action') == '3') { /* un-publish */
				db_execute("UPDATE graph_tree 
					SET enabled='',
					last_modified=NOW(),
					modified_by=" . $_SESSION['sess_user_id'] . '
					WHERE ' . array_to_sql_or($selected_items, 'id'));
			}
		}

		header('Location: tree.php?header=false');
		exit;
	}

	/* setup some variables */
	$tree_list = ''; $i = 0;

	/* loop through each of the selected items */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$tree_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array($matches[1]))) . '</li>';
			$tree_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('tree.php');

	html_start_box($tree_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');

	if (isset($tree_array) && sizeof($tree_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to delete the following Tree.', 'Click \'Continue\' to delete following Trees.', sizeof($tree_array)) . "</p>
					<div class='itemlist'><ul>$tree_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='" . __n('Delete Tree', 'Delete Trees', sizeof($tree_array)) . "'>";
		}elseif (get_nfilter_request_var('drp_action') == '2') { /* publish */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to publish the following Tree.', 'Click \'Continue\' to publish following Trees.', sizeof($tree_array)) . "</p>
					<div class='itemlist'><ul>$tree_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='" . __n('Publish Tree', 'Publish Trees', sizeof($tree_array)) . "'>";
		}elseif (get_nfilter_request_var('drp_action') == '3') { /* un-publish */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to un-publish the following Tree.', 'Click \'Continue\' to un-publish following Trees.', sizeof($tree_array)) . "</p>
					<div class='itemlist'><ul>$tree_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='" . __n('Un-publish Tree', 'Un-publish Trees', sizeof($tree_array)) . "'>";
		}
	}else{
		print "<tr><td class='odd'><span class='textError'>" . __('You must select at least one Tree.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($tree_array) ? serialize($tree_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ---------------------
    Tree Functions
   --------------------- */

function tree_edit() {
	global $fields_tree_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('type');
	/* ==================================================== */

	/* clean up search string */
	if (isset_request_var('filter')) {
		set_request_var('filter', sanitize_search_string(get_request_var('filter')));
	}

	load_current_session_value('filter', 'sess_tree_edit_filter', '');
	load_current_session_value('type', 'sess_tree_edit_type', '0');

	if (!isempty_request_var('id')) {
		$tree = db_fetch_row_prepared('SELECT * FROM graph_tree WHERE id = ?', array(get_request_var('id')));

		$header_label = __('Trees [edit: %s]', htmlspecialchars($tree['name']) );

		// Reset the cookie state if tree id has changed
		if (isset($_SESSION['sess_tree_id']) && $_SESSION['sess_tree_id'] != get_request_var('id')) {
			$select_first = true;
		}else{
			$select_first = false;
		}
		$_SESSION['sess_tree_id'] = get_request_var('id');
	}else{
		$tree = array();

		$header_label = __('Trees [new]');
	}

	form_start('tree.php', 'tree_edit');

	// Remove inherit from the main tree option
	unset($fields_tree_edit['sort_type']['array'][0]);

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_tree_edit, (isset($tree) ? $tree : array()))
		)
	);

	html_end_box();

	$lockdiv = '';

	if (isset($tree['locked']) && $tree['locked'] == 0) {
		$lockdiv = "<div style='padding:3px;'><table><tr><td><input id='lock' type='button' value='" . __('Edit Tree') . "'></td><td style='font-weight:bold;'>" . __('To Edit this tree, you must first lock it by pressing the Edit Tree button.') . "</td></tr></table></div>\n";
		$editable = false;
	}elseif (isset($tree['locked']) && $tree['locked'] == 1) {
		$lockdiv = "<div style='padding:3px;'><table><tr><td><input id='unlock' type='button' value='" . __('Finish Editing Tree') . "'></td><td><input id='addbranch' type='button' value='" . __('Add Root Branch') . "' onClick='createNode()'></td><td style='font-weight:bold;'>" . __('This tree has been locked for Editing on %1$s by %2$s.', $tree['locked_date'], get_username($tree['modified_by']));
		if ($tree['modified_by'] == $_SESSION['sess_user_id']) {
			$editable = true;
			$lockdiv .= '</td></tr></table></div>';
		}else{
			$editable = false;
			$lockdiv .= __('To edit the tree, you must first unlock it and then lock it as yourself') . '</td></tr></table></div>';
		}
	}else{
		$tree['id'] = 0;
		$editable = true;
	}

	if ($editable) {
		form_save_button('tree.php', 'return');
	}
		
	if (!isempty_request_var('id')) {
		print $lockdiv;

		print "<table class='treeTable' valign='top'><tr valign='top'><td class='treeArea'>\n";

		html_start_box( __('Tree Items'), '100%', '', '3', 'center', '');

		echo "<tr><td style='padding:7px;'><div id='jstree'></div></td></tr>\n";

		html_end_box();

		print "</td><td></td><td class='treeItemsArea'>\n";

		html_start_box( __('Available Devices'), '100%', '', '3', 'center', '');
		?>
		<tr id='treeFilter' class='even noprint'>
			<td>
			<form id='form_tree' action='tree.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search'); ?>
						</td>
						<td>
							<input id='hfilter' type='text' name='hfilter' size='25' value='<?php print htmlspecialchars(get_request_var('hfilter'));?>'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php	

		html_end_box(false);

		$display_text = array( __('Description'));

		html_start_box('', '100%', '', '3', 'center', '');
		html_header($display_text);

		echo "<tr><td style='padding:7px;'><div id='hosts'>\n";
		display_hosts();
		echo "</div></td></tr>\n";

		html_end_box();

		print "</td><td></td><td class='treeItemsArea'>\n";

		html_start_box( __('Available Graphs'), '100%', '', '3', 'center', '');
		?>
		<tr id='treeFilter' class='even noprint'>
			<td>
			<form id='form_tree' action='tree.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search'); ?>
						</td>
						<td>
							<input id='grfilter' type='text' name='grfilter' size='25' value='<?php print htmlspecialchars(get_request_var('grfilter'));?>'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php	
		html_end_box(false);

		$display_text = array( __('Graph Name'));

		html_start_box('', '100%', '', '3', 'center', '');
		html_header($display_text);

		echo "<tr><td style='padding:7px;'><div id='graphs'>\n";
		display_graphs();
		echo "</div></td></tr>\n";

		html_end_box();

		print "</td></tr></table>\n";

		?>
		<script type='text/javascript'>
		<?php
		if ($select_first) {
			print "var reset=true;\n";
		}else{
			print "var reset=false;\n";
		}
		?>

		var graphMeTimer;
		var hostMeTimer;
		var hostSortInfo   = {};
		var branchSortInfo = {};

		function createNode() {
			var ref = $('#jstree').jstree(true);
			sel = ref.create_node('#', 'New Node', '0');
			if (sel) {
				ref.edit(sel);
			}
		};

		function getGraphData() {
			$.get('tree.php?action=graphs&filter='+$('#grfilter').val(), function(data) {
				$('#graphs').jstree('destroy');
				$('#graphs').html(data);
				dragable('#graphs');
			});
		}

		function getHostData() {
			$.get('tree.php?action=hosts&filter='+$('#hfilter').val(), function(data) {
				$('#hosts').jstree('destroy');
				$('#hosts').html(data);
				dragable('#hosts');
			});
		}

		function setHostSortIcon(nodeid) {
			if (hostSortInfo[nodeid]) {
				// Already set
			}else{
				$.get('tree.php?action=get_host_sort&nodeid='+nodeid, function(data) {
					hostSortInfo[nodeid] = data;
				});
			}
		}

		function setBranchSortIcon(nodeid) {
			if (branchSortInfo[nodeid]) {
				// Already set
			}else{
				$.get('tree.php?action=get_branch_sort&nodeid='+nodeid, function(data) {
					branchSortInfo[nodeid] = data;
				});
			}
		}

		function getHostSortIcon(type, nodeid) {
			if (hostSortInfo[nodeid] == type) {
				return 'fa fa-check';
			}else{
				return 'false';
			}
		}

		function getBranchSortIcon(type, nodeid) {
			if (branchSortInfo[nodeid] == type) {
				return 'fa fa-check';
			}else{
				return 'false';
			}
		}

		function setBranchSortOrder(type, nodeid) {
			$.get('tree.php?action=set_branch_sort&type='+type+'&nodeid='+nodeid, function(data) {
				branchSortInfo[nodeid] = type;
			});
		}

		function setHostSortOrder(type, nodeid) {
			$.get('tree.php?action=set_host_sort&type='+type+'&nodeid='+nodeid, function(data) {
				hostSortInfo[nodeid] = type;
			});
		}

		graphsDropSet = '';
		hostsDropSet  = '';

		$(function() {
			<?php if ($editable == false) {?>
			$('select, input').not('#lock').prop('disabled', true);
			<?php }else{?>
			$('select, input').prop('disabled', false);
			<?php }?>

			$('form').unbind().submit(function(event) {
				event.preventDefault();

				if ($(this).attr('id') == 'tree_edit') {
					$.post('tree.php', { action: 'save', name: $('#name').val(), sort_type: $('#sort_type').val(), enabled: $('#enabled').is(':checked'), id: $('#id').val(), save_component_tree: 1, __csrf_magic: csrfMagicToken } ).done(function(data) {
						$('#main').html(data);
						applySkin();
					});
				}
			});

			$('#lock').click(function() {
				strURL = 'tree.php?action=lock&id=<?php print $tree['id'];?>&header=false';
				loadPageNoHeader(strURL);
			});

			$('#unlock').click(function() {
				strURL = 'tree.php?action=unlock&id=<?php print $tree['id'];?>&header=false';
				loadPageNoHeader(strURL);
			});

			var height      = parseInt($(window).height()-$('#jstree').offset().top-10)+'px';
			var hheight     = parseInt($(window).height()-$('#hosts').offset().top-10)+'px';
			var gheight     = parseInt($(window).height()-$('#graphs').offset().top-10)+'px';

			$(window).resize(function() {
				height      = parseInt($(window).height()-$('#jstree').offset().top-10)+'px';
				hheight     = parseInt($(window).height()-$('#hosts').offset().top-10)+'px';
				gheight     = parseInt($(window).height()-$('#graphs').offset().top-10)+'px';
				$('#jstree').css('height', height).css('overflow','auto');;
				$('#hosts').css('height', hheight).css('overflow','auto');;
				$('#graphs').css('height', gheight).css('overflow','auto');;
			});

			$("#jstree")
			.jstree({
				'types' : {
					'device' : {
						icon : 'images/server.png',
						max_children : 0
					},
					'graph' : {
						icon : 'images/server_chart_curve.png',
						max_children : 0
					}
				},
				'contextmenu' : {
					'items': function(node) {
						if (node.id.search('tgraph') > 0) {
							var dataType = 'graph';
						}else if (node.id.search('thost') > 0) {
							var dataType = 'host';
						}else {
							var dataType = 'branch';
						}
						if (dataType == 'graph') {
							return graphContext(node.id);
						}else if (dataType == 'host') {
							return hostContext(node.id);
						}else{
							return branchContext(node.id);
						}
					}
				},
				'core' : {
					'data' : {
						'url' : 'tree.php?action=get_node&tree_id='+$('#id').val(),
						'data' : function(node) {
							return { 'id' : node.id }
						}
					},
					'animation' : 0,
					'check_callback' : true,
					'force_text' : true
				},
				'themes' : {
					'name' : 'default',
					'responsive' : true,
					'url' : true,
					'dots' : false
				},
				'state': { 'key': 'tree_<?php print get_request_var('id');?>' },
				'plugins' : [ 'state', 'wholerow', <?php if ($editable) {?>'contextmenu', 'dnd', <?php }?>'types' ]
			})
			.on('ready.jstree', function(e, data) {
				if (reset == true) {
					$('#jstree').jstree('clear_state');
				}
			})<?php if ($editable) {?>.on('delete_node.jstree', function (e, data) {
				$.get('?action=delete_node', { 'id' : data.node.id, 'tree_id' : $('#id').val() })
					.always(function() {
						var st = data.instance.get_state();
						data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
					});
				})
			.on('hover_node.jstree', function (e, data) {
				// Enable accessibility
				$("[id*='"+data.node.id+"'] a:first").focus();

				if (data.node.id.search('thost') >= 0) {
					setHostSortIcon(data.node.id);
				}else if (data.node.id.search('thost') < 0 && data.node.id.search('tgraph') < 0) {
					setBranchSortIcon(data.node.id);
				}
			})
			.on('create_node.jstree', function (e, data) {
				$.get('?action=create_node', { 'id' : data.node.parent, 'tree_id' : $('#id').val(), 'position' : data.position, 'text' : data.node.text })
					.done(function (d) {
						data.instance.set_id(data.node, d.id);
						data.instance.set_text(data.node, d.text);
						data.instance.edit(data.node);
					})
					.fail(function () {
						var st = data.instance.get_state();
						data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
					});
			})
			.on('rename_node.jstree', function (e, data) {
				$.get('?action=rename_node', { 'id' : data.node.id, 'tree_id' : $('#id').val(), 'text' : data.text })
					.done(function (d) {
						if (d.result == 'false') {
							data.instance.set_text(data.node, d.text);
							data.instance.edit(data.node);
						}else{
							var st = data.instance.get_state();
							data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
						}
					});
			})
			.on('move_node.jstree', function (e, data) {
				$.get('?action=move_node', { 'id' : data.node.id, 'tree_id' : $('#id').val(), 'parent' : data.parent, 'position' : data.position })
					.always(function () {
						var st = data.instance.get_state();
						data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
					});
			})
			.on('copy_node.jstree', function (e, data) {
				oid = data.original.id;

				if (oid.search('thost') >= 0) {
					set = hostsDropSet;
				}else{
					set = graphsDropSet;
				}

				if (set != '' && set.selected.length > 0) {
					entries = set.selected;
					$.each(entries, function(i, id) {
						$.get('?action=copy_node', { 'id' : id, 'tree_id' : $('#id').val(), 'parent' : data.parent, 'position' : data.position })
							.always(function () {
								var st = data.instance.get_state();
								data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
							});
					});

					if (oid.search('thost') >= 0) {
						$('#hosts').jstree().deselect_all();
					}else{
						$('#graphs').jstree().deselect_all();
					}
				}else{
					$.get('?action=copy_node', { 'id' : data.original.id, 'tree_id' : $('#id').val(), 'parent' : data.parent, 'position' : data.position })
						.always(function () {
							var st = data.instance.get_state();
							data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
						});
				}
			})<?php }else{?>.children().bind('contextmenu', function(event) {
				return false;
			})<?php }?>;

			$('#jstree').css('height', height).css('overflow','auto');;

			dragable('#graphs', 'graphs');
			dragable('#hosts', 'hosts');
		});

		function dragable(element, type) {
			$(element)
				.jstree({
					'types' : {
						'device' : {
							icon : 'images/server.png',
							valid_children: 'none',
							max_children : 0
						},
						'graph' : {
							icon : 'images/server_chart_curve.png',
							valid_children: 'none',
							max_children : 0
						}
					},
					'core' : {
						'animation' : 0,
						'check_callback' : function(operation, node, node_parent, node_position, more) {
							return false;  // not dragging onto self
						}
					},
					'dnd' : {
						'always_copy' : true,
						'check_while_dragging': true
					},
					'themes' : { 'stripes' : true },
					'plugins' : [ 'wholerow', <?php if ($editable) {?>'dnd', <?php }?>'types' ]
				})
				.on('ready.jstree', function(e, data) {
					if (reset == true) {
						$('#jstree').jstree('clear_state');
					}
				})<?php if ($editable) {?>
				.on('select_node.jstree', function(e, data) {
					if (type == 'graphs') {
						graphsDropSet = data;
					}else{
						hostsDropSet  = data;
					}
				})
				.on('deselect_node.jstree', function(e,data) {
					if (type == 'graphs') {
						graphsDropSet = data;
					}else{
						hostsDropSet  = data;
					}
				})<?php }?>;
				$(element).find('.jstree-ocl').hide();
				$(element).children().bind('contextmenu', function(event) {
					return false;
				});
		}

		function branchContext(nodeid) {
			return {
				'create' : {
					'separator_before'	: false,
					'separator_after'	: true,
					'icon'				: 'fa fa-folder',
					'_disabled'			: false,
					'label'				: 'Create',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						inst.create_node(obj, {}, 'last', function (new_node) {
							setTimeout(function () { inst.edit(new_node); },0);
						});
					}
				},
				'rename' : {
					'separator_before'	: false,
					'separator_after'	: false,
					'icon'				: 'fa fa-pencil',
					'_disabled'			: false,
					'label'				: 'Rename',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						inst.edit(obj);
					}
				},
				'remove' : {
					'separator_before'	: false,
					'icon'				: 'fa fa-remove',
					'separator_after'	: false,
					'_disabled'			: false,
					'label'				: 'Delete',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						} else {
							inst.delete_node(obj);
						}
					}
				},
				'bst' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-sort',
					'separator_after'	: false,
					'label'				: 'Branch Sorting',
					'action'			: false,
					'submenu' : {
						'inherit' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: getBranchSortIcon('inherit', nodeid),
							'label'				: 'Inherit',
							'action'			: function (data) {
								setBranchSortOrder('inherit', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function() { this.set_state(st); });
							}
						},
						'manual' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: getBranchSortIcon('manual', nodeid),
							'label'				: 'Manual',
							'action'			: function (data) {
								setBranchSortOrder('manual', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function() { this.set_state(st); });
							}
						},
						'alpha' : {
							'separator_before'	: false,
							'icon'				: getBranchSortIcon('alpha', nodeid),
							'separator_after'	: false,
							'label'				: 'Alphabetic',
							'action'			: function (data) {
								setBranchSortOrder('alpha', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function() { this.set_state(st); });
							}
						},
						'natural' : {
							'separator_before'	: false,
							'icon'				: getBranchSortIcon('natural', nodeid),
							'separator_after'	: false,
							'label'				: 'Natural',
							'action'			: function (data) {
								setBranchSortOrder('natural', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function () { this.set_state(st); });
							}
						},
						'numeric' : {
							'separator_before'	: false,
							'icon'				: getBranchSortIcon('numeric', nodeid),
							'separator_after'	: false,
							'label'				: 'Numeric',
							'action'			: function (data) {
								setBranchSortOrder('numeric', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function () { this.set_state(st); });
							}
						}
					}
				},
				'ccp' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-edit',
					'separator_after'	: false,
					'label'				: 'Edit',
					'action'			: false,
					'submenu' : {
						'cut' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: 'fa fa-cut',
							'label'				: 'Cut',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.cut(inst.get_selected());
								} else {
									inst.cut(obj);
								}
							}
						},
						'copy' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-copy',
							'separator_after'	: false,
							'label'				: 'Copy',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.copy(inst.get_selected());
								} else {
									inst.copy(obj);
								}
							}
						},
						'paste' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-paste',
							'_disabled'			: function (data) {
								return !$.jstree.reference(data.reference).can_paste();
							},
							'separator_after'	: false,
							'label'				: 'Paste',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								inst.paste(obj);
							}
						}
					}
				}
			};
		}

		function graphContext(nodeid) {
			return {
				'remove' : {
					'separator_before'	: false,
					'icon'				: 'fa fa-remove',
					'separator_after'	: false,
					'_disabled'			: false, //(this.check('delete_node', data.reference, this.get_parent(data.reference), '')),
					'label'				: 'Delete',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						} else {
							inst.delete_node(obj);
						}
					}
				},
				'ccp' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-edit',
					'separator_after'	: false,
					'label'				: 'Edit',
					'action'			: false,
					'submenu' : {
						'cut' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: 'fa fa-cut',
							'label'				: 'Cut',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.cut(inst.get_selected());
								} else {
									inst.cut(obj);
								}
							}
						},
						'copy' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-copy',
							'separator_after'	: false,
							'label'				: 'Copy',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.copy(inst.get_selected());
								} else {
									inst.copy(obj);
								}
							}
						}
					}
				}
			};
		}

		function hostContext(nodeid) {
			return {
				'remove' : {
					'separator_before'	: false,
					'icon'				: 'fa fa-remove',
					'separator_after'	: false,
					'_disabled'			: false,
					'label'				: 'Delete',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						} else {
							inst.delete_node(obj);
						}
					}
				},
				'hso' : {
					'separator_before'	: true,
					'separator_after'	: false,
					'icon'				: 'fa fa-sort',
					'label'				: 'Sorting Type',
					'action'			: false,
					'submenu' : {
						'hsgt' : {
							'separator_before'	: false,
							'icon'				: getHostSortIcon('hsgt', nodeid),
							'separator_after'	: false,
							'label'				: 'Graph Template',
							'action'			: function (data) {
								setHostSortOrder('hsgt', nodeid);
							}
						},
						'hsdq' : {
							'separator_before'	: false,
							'icon'				: getHostSortIcon('hsdq', nodeid),
							'separator_after'	: false,
							'label'				: 'Data Query Index',
							'action'			: function (data) {
								setHostSortOrder('hsdq', nodeid);
							}
						}
					}
				},
				'ccp' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-edit',
					'separator_after'	: false,
					'label'				: 'Edit',
					'action'			: false,
					'submenu' : {
						'cut' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: 'fa fa-cut',
							'label'				: 'Cut',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.cut(inst.get_selected());
								} else {
									inst.cut(obj);
								}
							}
						},
						'copy' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-copy',
							'separator_after'	: false,
							'label'				: 'Copy',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.copy(inst.get_selected());
								} else {
									inst.copy(obj);
								}
							}
						}
					}
				}
			};
		}

		$('#grfilter').keyup(function(data) {
			graphMeTimer && clearTimeout(graphMeTimer);
			graphMeTimer = setTimeout(getGraphData, 300);
		});

		$('#hfilter').keyup(function(data) {
			hostMeTimer && clearTimeout(hostMeTimer);
			hostMeTimer = setTimeout(getHostData, 300);
		});
		</script>
		<?php
	}
}

function display_hosts() {
	if (get_request_var('filter') != '') {
		$sql_where = "h.hostname LIKE '%" . get_request_var('filter') . "%' OR h.description LIKE '%" . get_request_var('filter') . "%'";
	}else{
		$sql_where = '';
	}

	$hosts = get_allowed_devices($sql_where, 'description', '20');

	if (sizeof($hosts)) {
		foreach($hosts as $h) {
			echo "<ul><li id='thost:" . $h['id'] . "' data-jstree='{ \"type\" : \"device\"}'>" . $h['description'] . ' (' . $h['hostname'] . ')' . "</li></ul>\n";
		}
	}
}

function display_graphs() {
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (title_cache LIKE '%" . get_request_var('filter') . "%' OR gt.name LIKE '%" . get_request_var('filter') . "%') AND local_graph_id>0";
	}else{
		$sql_where = 'WHERE local_graph_id>0';
	}

	$graphs = db_fetch_assoc("SELECT 
		gtg.local_graph_id AS id, 
		gtg.title_cache AS title,
		gt.name AS template_name
		FROM graph_templates_graph AS gtg
		LEFT JOIN graph_templates AS gt
		ON gt.id=gtg.graph_template_id
		$sql_where 
		ORDER BY title_cache 
		LIMIT 20");

	if (sizeof($graphs)) {
		foreach($graphs as $g) {
			if (is_graph_allowed($g['id'])) {
				echo "<ul><li id='tgraph:" . $g['id'] . "' data-jstree='{ \"type\": \"graph\" }'>" . $g['title'] . '</li></ul>';	
			}
		}
	}
}

function tree() {
	global $tree_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK, 
			'pageset' => true,
			'default' => '', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'sequence', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'ASC', 
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_tree');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'tree.php?rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'tree.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_tree').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>

	<?php

	html_start_box( __('Trees'), '100%', '', '3', 'center', 'tree.php?action=edit');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_tree' action='tree.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search'); ?>
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						<?php print __('Trees'); ?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='refresh' value='<?php print __('Go');?>' title='<?php print __('Set/Refresh Filters');?>'>
					</td>
					<td>
						<input type='button' id='clear' value='<?php print __('Clear');?>' title='<?php print __('Clear Filters');?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print get_request_var('page');?>'>
		</form>
		</td>
	</tr>
	<?php	

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var('filter'))) {
		$sql_where = "WHERE (t.name LIKE '%" . get_request_var('filter') . "%' OR ti.title LIKE '%" . get_request_var('filter') . "%')";
	}else{
		$sql_where = '';
	}

	$trees = db_fetch_assoc("SELECT t.*,
		SUM(CASE WHEN ti.host_id>0 THEN 1 ELSE 0 END) AS hosts,
		SUM(CASE WHEN ti.local_graph_id>0 THEN 1 ELSE 0 END) AS graphs,
		SUM(CASE WHEN ti.local_graph_id=0 AND host_id=0 THEN 1 ELSE 0 END) AS branches
		FROM graph_tree AS t
		LEFT JOIN graph_tree_items AS ti
		ON t.id=ti.graph_tree_id
		$sql_where
		GROUP BY t.id
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows);

	$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT(ti.graph_tree_id))
		FROM graph_tree AS t
		LEFT JOIN graph_tree_items AS ti
		ON t.id=ti.graph_tree_id
		$sql_where");

	$nav = html_nav_bar('tree.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Trees'), 'page', 'main');

	form_start('tree.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name' => array('display' => __('Tree Name'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('The name by which this Tree will be referred to as.')),
		'id' => array('display' => __('ID'), 'align' => 'right', 'sort' => 'ASC', 'tip' => __('The internal database ID for this Tree.  Useful when performing automation or debugging.')),
		'enabled' => array('display' => __('Published'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('Unpublished Trees cannot be viewed from the Graph tab')),
		'locked' => array('display' => __('Locked'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('A Tree must be locked in order to be edited.')),
		'user_id' => array('display' => __('Owner'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('The original author of this Tree.')),
		'sequence' => array('display' => __('Order'), 'align' => 'center', 'sort' => 'ASC', 'tip' => __('To change the order of the trees, first sort by this column, press the up or down arrows once they appear.')),
		'last_modified' => array('display' => __('Last Edited'), 'align' => 'right', 'sort' => 'ASC', 'tip' => __('The date that this Tree was last edited.')),
		'modified_by' => array('display' => __('Edited By'), 'align' => 'right', 'sort' => 'ASC', 'tip' => __('The last user to have modified this Tree.')),
		'branches' => array('display' => __('Branches'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __('The total number of Branches in this Tree.')),
		'hosts' => array('display' => __('Devices'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __('The total number of individual Devices in this Tree.')),
		'graphs' => array('display' => __('Graphs'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __('The total number of individual Graphs in this Tree.')));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 1;
	if (sizeof($trees)) {
		foreach ($trees as $tree) {
			$sequence = '';
			if (get_request_var('sort_column') == 'sequence' && get_request_var('sort_direction') == 'ASC') {
				if ($i == 1) {
					$sequence .= '<a class="pic fa fa-caret-down moveArrow" href="' . htmlspecialchars('tree.php?action=tree_down&id=' . $tree['id']) . '" title="' . __('Move Down') . '"></a>';
					$sequence .= '<span class="moveArrowNone"></span>';
				}elseif ($i == sizeof($trees)) {
					$sequence .= '<span class="moveArrowNone"></span>';
					$sequence .= '<a class="pic fa fa-caret-up moveArrow" href="' . htmlspecialchars('tree.php?action=tree_up&id=' . $tree['id']) . '" title="' . __('Move Down') . '"></a>';
					
				}else{
					$sequence .= '<a class="pic fa fa-caret-down moveArrow" href="' . htmlspecialchars('tree.php?action=tree_down&id=' . $tree['id']) . '" title="' . __('Move Down') . '"></a>';
					$sequence .= '<a class="pic fa fa-caret-up moveArrow" href="' . htmlspecialchars('tree.php?action=tree_up&id=' . $tree['id']) . '" title="' . __('Move Down') . '"></a>';
				}
			}

			form_alternate_row('line' . $tree['id'], true);
			form_selectable_cell(filter_value($tree['name'], get_request_var('filter'), 'tree.php?action=edit&id=' . $tree['id']), $tree['id']);
			form_selectable_cell($tree['id'], $tree['id'], '', 'text-align:right');
			form_selectable_cell($tree['enabled'] == 'on' ? __('Yes'):__('No'), $tree['id']);
			form_selectable_cell($tree['locked'] == '1' ? __('Yes'):__('No'), $tree['id']);
			form_selectable_cell(get_username($tree['user_id']), $tree['id']);
			form_selectable_cell($sequence, $tree['id'], '', 'nowrap center');
			form_selectable_cell(substr($tree['last_modified'],0,16), $tree['id'], '', 'text-align:right');
			form_selectable_cell(get_username($tree['modified_by']), $tree['id'], '', 'text-align:right');
			form_selectable_cell($tree['branches'] > 0 ? number_format_i18n($tree['branches']):'-', $tree['id'], '', 'text-align:right');
			form_selectable_cell($tree['hosts'] > 0 ? number_format_i18n($tree['hosts']):'-', $tree['id'], '', 'text-align:right');
			form_selectable_cell($tree['graphs'] > 0 ? number_format_i18n($tree['graphs']):'-', $tree['id'], '', 'text-align:right');
			form_checkbox_cell($tree['name'], $tree['id']);
			form_end_row();

			$i++;
		}
	}else{
		print "<tr class='tableRow'><td colspan='11'><em>" . __('No Trees Found') . "</em></td></tr>";
	}
	html_end_box(false);

	if (sizeof($trees)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($tree_actions);

	form_end();
}


<?php
/*
Plugin Name: WP Advanced Role Manager
Plugin URI: https://shariati.me
Description: A comprehensive role management system for WordPress, allowing administrators to manage roles, capabilities, and more.
Version: 1.0
Author: Amin Shariati
Author URI: https://shariati.me
License: GPL2
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the admin menu
 */
add_action('admin_menu', 'arm_manage_user_roles_menu');

function arm_manage_user_roles_menu() {
    // Add "Add New Role" submenu under the "Users" menu
    add_submenu_page(
        'users.php',
        'Add New Role',
        'Add New Role',
        'manage_options',
        'arm-add-new-role',
        'arm_add_new_role_page'
    );

    // Add "All Roles" submenu under the "Users" menu
    add_submenu_page(
        'users.php',
        'All Roles',
        'All Roles',
        'manage_options',
        'arm-all-roles',
        'arm_all_roles_page'
    );

    // Hidden pages for Edit, Delete, View, and Import/Export actions
    add_submenu_page(null, 'Edit User Role', 'Edit User Role', 'manage_options', 'arm-edit-user-role', 'arm_edit_user_role_callback');
    add_submenu_page(null, 'Delete User Role', 'Delete User Role', 'manage_options', 'arm-delete-user-role', 'arm_delete_user_role_callback');
    add_submenu_page(null, 'View User Role', 'View User Role', 'manage_options', 'arm-view-user-role', 'arm_view_user_role_callback');
    add_submenu_page(null, 'Import/Export Roles', 'Import/Export Roles', 'manage_options', 'arm-import-export-roles', 'arm_import_export_roles_callback');
}

/**
 * Helper function to get all capabilities grouped by category
 */
function arm_get_all_capabilities() {
    return [
        'Post Management' => ['read', 'edit_posts', 'delete_posts', 'publish_posts'],
        'User Management' => ['list_users', 'create_users', 'delete_users'],
        // Add more groups as needed
    ];
}

/**
 * Callback function for "Add New Role" page
 */
function arm_add_new_role_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['action']) && $_POST['action'] === 'add_role') {
        check_admin_referer('arm_user_roles_nonce');
        $role_name = sanitize_text_field($_POST['role_name']);
        $role_description = sanitize_text_field($_POST['role_description']);
        $role_capabilities = []; // Default empty capabilities
        add_role($role_name, ucfirst($role_description), $role_capabilities);
    }

    ?>
    <div class="wrap">
        <h1>Add New Role</h1>
        <form method="post">
            <?php wp_nonce_field('arm_user_roles_nonce'); ?>
            <input type="hidden" name="action" value="add_role">
            <label for="role_name">Role Name:</label>
            <input type="text" name="role_name" id="role_name" required>
            <label for="role_description">Description:</label>
            <input type="text" name="role_description" id="role_description" required>
            <input type="submit" class="button button-primary" value="Add Role">
        </form>
    </div>
    <?php
}

/**
 * Callback function for "All Roles" page
 */
function arm_all_roles_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['action'])) {
        check_admin_referer('arm_user_roles_nonce');

        if ($_POST['action'] === 'delete_role' && !empty($_POST['role_to_delete'])) {
            $role_to_delete = sanitize_text_field($_POST['role_to_delete']);
            remove_role($role_to_delete);
        }
    }

    $user_roles_table = new ARM_User_Roles_Table();
    $user_roles_table->prepare_items();

    ?>
    <div class="wrap">
        <h1>All Roles</h1>
        <?php $user_roles_table->display(); ?>
    </div>
    <?php
}

/**
 * Callback function for editing a user role
 */
function arm_edit_user_role_callback() {
    if (!current_user_can('manage_options')) wp_die(__('You do not have sufficient permissions to access this page.'));

    $role_key = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : '';
    global $wp_roles;
    if (!$role_key || !$wp_roles->is_role($role_key)) {
        echo '<h1>Invalid Role</h1>';
        return;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_role') {
        check_admin_referer('arm_edit_user_role_nonce');
        $new_role_name = sanitize_text_field($_POST['new_role_name']);
        $capabilities = isset($_POST['capabilities']) ? $_POST['capabilities'] : [];

        // Update role name
        $wp_roles->roles[$role_key]['name'] = $new_role_name;
        $wp_roles->role_objects[$role_key]->name = $new_role_name;

        // Update role capabilities
        $role = get_role($role_key);
        if ($role) {
            foreach ($role->capabilities as $cap => $enabled) $role->remove_cap($cap);
            foreach ($capabilities as $cap) $role->add_cap($cap);
        }

        echo '<div class="updated"><p>Role updated successfully!</p></div>';
    }

    $role_name = $wp_roles->roles[$role_key]['name'];
    $current_capabilities = $wp_roles->roles[$role_key]['capabilities'];
    $all_capabilities = arm_get_all_capabilities();

    ?>
    <div class="wrap">
        <h1>Edit User Role: <?php echo esc_html($role_name); ?></h1>
        <form method="post">
            <?php wp_nonce_field('arm_edit_user_role_nonce'); ?>
            <input type="hidden" name="action" value="update_role">
            <label for="new_role_name">New Role Name:</label>
            <input type="text" name="new_role_name" id="new_role_name" value="<?php echo esc_attr($role_name); ?>" required>

            <h2>Capabilities</h2>
            <?php foreach ($all_capabilities as $group => $caps): ?>
                <h3><?php echo esc_html($group); ?></h3>
                <?php foreach ($caps as $capability): ?>
                    <label>
                        <input type="checkbox" name="capabilities[]" value="<?php echo esc_attr($capability); ?>" <?php checked(isset($current_capabilities[$capability]), true); ?>>
                        <?php echo esc_html($capability); ?>
                    </label><br>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <input type="submit" class="button button-primary" value="Update Role">
        </form>
    </div>
    <?php
}

/**
 * Callback function for deleting a user role
 */
function arm_delete_user_role_callback() {
    if (!current_user_can('manage_options')) wp_die(__('You do not have sufficient permissions to access this page.'));

    $role_key = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : '';
    global $wp_roles;
    if (!$role_key || !$wp_roles->is_role($role_key)) {
        echo '<h1>Invalid Role</h1>';
        return;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_role') {
        check_admin_referer('arm_delete_user_role_nonce');
        remove_role($role_key);
        echo '<div class="updated"><p>Role deleted successfully!</p></div>';
        return;
    }

    ?>
    <div class="wrap">
        <h1>Delete User Role: <?php echo esc_html($role_key); ?></h1>
        <p>Are you sure you want to delete this role? This action cannot be undone.</p>
        <form method="post">
            <?php wp_nonce_field('arm_delete_user_role_nonce'); ?>
            <input type="hidden" name="action" value="delete_role">
            <input type="submit" class="button button-primary" value="Delete Role">
        </form>
    </div>
    <?php
}

/**
 * Callback function for viewing a user role
 */
function arm_view_user_role_callback() {
    if (!current_user_can('manage_options')) wp_die(__('You do not have sufficient permissions to access this page.'));

    $role_key = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : '';
    global $wp_roles;
    if (!$role_key || !$wp_roles->is_role($role_key)) {
        echo '<h1>Invalid Role</h1>';
        return;
    }

    $role_name = $wp_roles->roles[$role_key]['name'];
    $capabilities = $wp_roles->roles[$role_key]['capabilities'];
    ?>
    <div class="wrap">
        <h1>View User Role: <?php echo esc_html($role_name); ?></h1>
        <h2>Capabilities:</h2>
        <ul>
            <?php foreach ($capabilities as $capability => $enabled) : ?>
                <li><?php echo esc_html($capability); ?>: <?php echo $enabled ? 'Enabled' : 'Disabled'; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * Callback function for importing/exporting roles
 */
function arm_import_export_roles_callback() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'export_roles') {
            $roles = wp_roles()->roles;
            $json_roles = json_encode($roles);
            header('Content-disposition: attachment; filename=roles.json');
            header('Content-type: application/json');
            echo $json_roles;
            exit;
        } elseif ($_POST['action'] === 'import_roles') {
            check_admin_referer('arm_import_roles_nonce');
            $json_roles = file_get_contents($_FILES['roles_file']['tmp_name']);
            $roles = json_decode($json_roles, true);
            foreach ($roles as $role_key => $role_data) {
                add_role($role_key, $role_data['name'], $role_data['capabilities']);
            }
            echo '<div class="updated"><p>Roles imported successfully!</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1>Import/Export Roles</h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('arm_import_roles_nonce'); ?>
            <h2>Export Roles</h2>
            <input type="hidden" name="action" value="export_roles">
            <input type="submit" class="button button-primary" value="Export Roles as JSON">
        </form>
        <form method="post" enctype="multipart/form-data">
            <h2>Import Roles</h2>
            <input type="file" name="roles_file" accept=".json" required>
            <input type="hidden" name="action" value="import_roles">
            <input type="submit" class="button button-primary" value="Import Roles">
        </form>
    </div>
    <?php
}

/**
 * Custom class to create the User Roles table
 */
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ARM_User_Roles_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'user_role',
            'plural'   => 'user_roles',
            'ajax'     => false
        ]);
    }

    // Prepare the items for the table
    public function prepare_items() {
        global $wp_roles;
        $this->items = $wp_roles->roles;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];

        $this->_column_headers = [$columns, $hidden, $sortable];
    }

    // Define the columns
    public function get_columns() {
        return [
            'role'        => 'Role',
            'description' => 'Description'
        ];
    }

    // Render the Role column with action links
    public function column_role($item) {
        $role_key = array_search($item, $GLOBALS['wp_roles']->roles, true);
        $role_name = esc_html($item['name']);

        $actions = [
            'edit'   => sprintf('<a href="%s">Edit</a>', admin_url('users.php?page=arm-edit-user-role&role=' . urlencode($role_key))),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'Are you sure you want to delete this role?\');">Delete</a>', admin_url('users.php?page=arm-delete-user-role&role=' . urlencode($role_key))),
            'view'   => sprintf('<a href="%s">View</a>', admin_url('users.php?page=arm-view-user-role&role=' . urlencode($role_key)))
        ];

        return sprintf('%1$s %2$s', $role_name, $this->row_actions($actions));
    }

    // Render the Description column
    public function column_description($item) {
        return esc_html($item['name']); // Customize if necessary
    }
}

/**
 * End of WP Advanced Role Manager Plugin
 */

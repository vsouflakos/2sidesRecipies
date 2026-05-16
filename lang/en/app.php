<?php

return [
    'nav' => [
        'users' => 'Users',
        'dashboard' => 'Dashboard',
    ],
    'admin' => [
        'page_heading' => 'User Management',
        'search_placeholder' => 'Search by name or email…',
        'result_count' => 'Showing :shown of :total users',
        'empty_heading' => 'No users found',
        'empty_body' => 'No users match your search. Try a different name or email.',
        'toast_role_changed' => 'Role updated — :name is now a :role.',
        'toast_activated' => ":name's account has been reactivated.",
        'toast_deactivated' => ":name's account has been deactivated.",
        'toast_deleted' => ":name's account has been deleted.",
        'toast_error' => 'Something went wrong. Please try again.',
        'deactivate_title' => 'Deactivate account?',
        'deactivate_body' => "Deactivating :name's account will prevent them from logging in. You can reactivate it at any time.",
        'deactivate_dismiss' => 'Keep account active',
        'deactivate_confirm' => 'Deactivate account',
        'delete_title' => 'Delete account?',
        'delete_body' => "This will permanently delete :name's account and all their data. This action cannot be undone.",
        'delete_dismiss' => 'Keep account',
        'delete_confirm' => 'Delete account',
        'guard_self_role' => 'You cannot change your own role',
        'guard_self_deactivate' => 'You cannot deactivate your own account',
        'guard_self_delete' => 'You cannot delete your own account',
        'guard_last_admin_role' => 'Cannot remove the last Admin role',
        'guard_last_admin_deactivate' => 'Cannot deactivate the last Admin',
        'guard_last_admin_delete' => 'Cannot delete the last Admin',
    ],
    'auth' => [
        'deactivated' => 'Your account has been deactivated. Contact an administrator.',
    ],
    'language' => [
        'en' => 'EN',
        'el' => 'EL',
    ],
];

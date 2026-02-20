
<?php
// Prevent multiple inclusion
if (!defined('AUTH_INCLUDED')) {
    define('AUTH_INCLUDED', true);

    session_start();

    // List of employee-allowed pages
    define('EMPLOYEE_ALLOWED_PAGES', array(
        'purchase.php',
        'items.php', 
        
    ));

    function check_login() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
    }

    function check_owner() {
        check_login();
        if ($_SESSION['role'] !== 'owner') {
            // NOTE: The check_owner function is generally used for pages only owners can see.
            // If the user is an employee, the next function 'is_page_restricted' handles access to specific pages.
            // We will keep 'restricted.php' for this function's logic, though 'sales_report.php' won't use it directly now.
            header("Location: restricted.php");
            exit;
        }
    }

    function check_employee() {
        check_login();
        if ($_SESSION['role'] !== 'employee') {
            header("Location: no_access.php");
            exit;
        }
    }

    function is_page_restricted() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
            return false;
        }
        
        $current_page = basename($_SERVER['PHP_SELF']);
        // The sales_report.php is now restricted for employees by omission from the allowed list
        return !in_array($current_page, EMPLOYEE_ALLOWED_PAGES);
    }

    function has_temporary_access() {
        return isset($_SESSION['temp_access_granted']) && $_SESSION['temp_access_granted'] > time();
    }

    function verify_access_key($key) {
        // Hash of "owner123" - you should change this
       $valid_key = "81dc9bdb52d04dc20036dbd8313ed055"; // Use your current working hash
        
        if (md5($key) === $valid_key) {
            // Set access for 10 seconds
            $_SESSION['temp_access_granted'] = time() + 10; 
            return true;
        }
        return false;
    }
}
?>

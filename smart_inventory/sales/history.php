<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once __DIR__ . '/../includes/role_helpers.php';


/* =========================
   CHECK LOGIN
========================= */

if (!isset($_SESSION['user_id'])) {

    header(
        "Location: /smart_inventory/login.php"
    );

    exit();
}


if (!in_array($_SESSION['role'] ?? 'user', ['admin', 'staff'], true)) {
    redirectToRoleDashboard($_SESSION['role'] ?? 'user');
}

require_once "../config/database.php";


/* =========================
   USER INFORMATION
========================= */

$user_id =
    intval(
        $_SESSION['user_id']
    );

$user_name =
    $_SESSION['name']
    ?? 'User';

$user_role =
    $_SESSION['role']
    ?? 'staff';


/* =========================
   DASHBOARD LINK
========================= */

if ($user_role === 'admin') {

    $dashboardUrl =
        "/smart_inventory/admin/dashboard.php";

} else {

    $dashboardUrl =
        "/smart_inventory/staff/dashboard.php";
}


/* =========================
   FILTER VALUES
========================= */

$search =
    trim(
        $_GET['search']
        ?? ''
    );


$start_date =
    trim(
        $_GET['start_date']
        ?? ''
    );


$end_date =
    trim(
        $_GET['end_date']
        ?? ''
    );


/* =========================
   BUILD SALES QUERY
========================= */

$sql =
    "SELECT
        sales.id,
        sales.transaction_number,
        sales.user_id,
        sales.total_amount,
        sales.total_cost,
        sales.total_profit,
        sales.sale_date,
        users.name AS cashier_name

     FROM sales

     LEFT JOIN users
        ON sales.user_id = users.id

     WHERE 1 = 1";


$params = [];

$types = "";


/* =========================
   STAFF PERMISSION

   Staff can only see
   their own transactions.
========================= */

if ($user_role === 'staff') {

    $sql .=
        " AND sales.user_id = ?";

    $types .=
        "i";

    $params[] =
        $user_id;
}


/* =========================
   SEARCH TRANSACTION
========================= */

if ($search !== '') {

    $sql .=
        " AND sales.transaction_number
          LIKE ?";

    $types .=
        "s";

    $params[] =
        "%" .
        $search .
        "%";
}


/* =========================
   START DATE
========================= */

if ($start_date !== '') {

    $sql .=
        " AND DATE(sales.sale_date) >= ?";

    $types .=
        "s";

    $params[] =
        $start_date;
}


/* =========================
   END DATE
========================= */

if ($end_date !== '') {

    $sql .=
        " AND DATE(sales.sale_date) <= ?";

    $types .=
        "s";

    $params[] =
        $end_date;
}


/* =========================
   ORDER RESULTS
========================= */

$sql .=
    " ORDER BY
        sales.sale_date DESC,
        sales.id DESC";


/* =========================
   PREPARE QUERY
========================= */

$stmt =
    $conn->prepare(
        $sql
    );


if (!$stmt) {

    die(
        "Query error: " .
        $conn->error
    );
}


/* =========================
   BIND DYNAMIC PARAMETERS
========================= */

if (!empty($params)) {

    $bindValues = [];

    foreach (
        $params
        as $key => $value
    ) {

        $bindValues[$key] =
            &$params[$key];
    }


    $stmt->bind_param(
        $types,
        ...$bindValues
    );
}


$stmt->execute();


$result =
    $stmt->get_result();


/* =========================
   STORE SALES
========================= */

$salesRows = [];

$totalResultSales = 0;

$totalResultProfit = 0;


while (
    $sale =
    $result->fetch_assoc()
) {

    $salesRows[] =
        $sale;


    $totalResultSales +=
        floatval(
            $sale[
                'total_amount'
            ]
        );


    if ($user_role === 'admin') {

        $totalResultProfit +=
            floatval(
                $sale[
                    'total_profit'
                ]
            );
    }
}


$totalTransactions =
    count(
        $salesRows
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Sales History | Smart Inventory
</title>


<style>

/* =========================
   GLOBAL
========================= */

* {

    box-sizing:
        border-box;

    margin: 0;

    padding: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


:root {

    --dark:
        #111111;

    --dark-light:
        #1b1b1b;

    --primary:
        #111111;

    --primary-dark:
        #000000;

    --green:
        #292929;

    --purple:
        #333333;

    --orange:
        #555555;

    --background:
        #f4f7fb;

    --white:
        #ffffff;

    --text:
        #111111;

    --muted:
        #707070;

    --border:
        #e3e3e3;
}


body {

    min-height:
        100vh;

    background:
        var(--background);

    color:
        var(--text);
}


/* =========================
   NAVBAR
========================= */

.navbar {

    min-height:
        72px;

    background:
        var(--dark);

    padding:
        0 26px;

    color:
        white;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        20px;

    box-shadow:
        0 3px 12px
        rgba(
            15,
            23,
            42,
            0.18
        );
}


.brand {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;
}


.brand-icon {

    width:
        43px;

    height:
        43px;

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    border-radius:
        11px;

    background:
        linear-gradient(
            135deg,
            #111111,
            #555555
        );

    font-size:
        20px;
}


.brand h2 {

    font-size:
        16px;
}


.brand small {

    display:
        block;

    margin-top:
        3px;

    font-size:
        10px;

    color:
        #9a9a9a;
}


/* =========================
   NAVIGATION
========================= */

.nav-right {

    display:
        flex;

    align-items:
        center;

    gap:
        15px;
}


.user-info {

    text-align:
        right;
}


.user-info strong {

    display:
        block;

    font-size:
        12px;
}


.user-info span {

    color:
        #9a9a9a;

    font-size:
        10px;

    text-transform:
        capitalize;
}


.nav-links {

    display:
        flex;

    align-items:
        center;

    gap:
        4px;
}


.nav-links a {

    color:
        #d0d0d0;

    padding:
        9px 11px;

    border-radius:
        7px;

    text-decoration:
        none;

    font-size:
        12px;

    transition:
        0.2s;
}


.nav-links a:hover {

    color:
        white;

    background:
        var(--dark-light);
}


.nav-links .active {

    background:
        var(--primary);

    color:
        white;
}


.nav-links .logout {

    color:
        #dddddd;
}


/* =========================
   CONTAINER
========================= */

.container {

    width:
        100%;

    max-width:
        1500px;

    margin:
        auto;

    padding:
        28px;
}


/* =========================
   PAGE HEADER
========================= */

.page-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;

    margin-bottom:
        23px;
}


.page-header h1 {

    font-size:
        27px;

    margin-bottom:
        5px;
}


.page-header p {

    color:
        var(--muted);

    font-size:
        13px;
}


.back-button {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    padding:
        10px 14px;

    background:
        white;

    color:
        var(--text);

    border:
        1px solid
        var(--border);

    border-radius:
        8px;

    text-decoration:
        none;

    font-size:
        12px;

    font-weight:
        bold;

    transition:
        0.2s;
}


.back-button:hover {

    border-color:
        #bdbdbd;

    background:
        #f5f5f5;
}


/* =========================
   SUMMARY CARDS
========================= */

.summary-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap:
        17px;

    margin-bottom:
        22px;
}


.summary-grid.staff {

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );
}


.summary-card {

    background:
        white;

    padding:
        20px;

    border:
        1px solid
        var(--border);

    border-radius:
        13px;

    box-shadow:
        0 4px 15px
        rgba(
            15,
            23,
            42,
            0.04
        );
}


.summary-top {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        13px;
}


.summary-label {

    color:
        var(--muted);

    font-size:
        11px;

    font-weight:
        bold;

    text-transform:
        uppercase;
}


.summary-icon {

    width:
        40px;

    height:
        40px;

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    border-radius:
        10px;

    font-size:
        18px;
}


.blue {

    color:
        #000000;

    background:
        #eeeeee;
}


.green {

    color:
        #111111;

    background:
        #eeeeee;
}


.purple {

    color:
        #333333;

    background:
        #eeeeee;
}


.summary-card h2 {

    font-size:
        25px;
}


/* =========================
   FILTER PANEL
========================= */

.filter-panel {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        13px;

    padding:
        20px;

    margin-bottom:
        20px;

    box-shadow:
        0 4px 15px
        rgba(
            15,
            23,
            42,
            0.04
        );
}


.filter-title {

    margin-bottom:
        15px;
}


.filter-title h2 {

    font-size:
        16px;

    margin-bottom:
        3px;
}


.filter-title p {

    color:
        var(--muted);

    font-size:
        11px;
}


.filter-form {

    display:
        grid;

    grid-template-columns:
        2fr 1fr 1fr auto;

    gap:
        12px;

    align-items:
        end;
}


.form-group label {

    display:
        block;

    color:
        #333333;

    font-size:
        11px;

    font-weight:
        bold;

    margin-bottom:
        6px;
}


.form-group input {

    width:
        100%;

    height:
        42px;

    border:
        1px solid
        #d0d0d0;

    border-radius:
        8px;

    background:
        #fafafa;

    padding:
        0 12px;

    outline:
        none;

    font-size:
        13px;

    transition:
        0.2s;
}


.form-group input:focus {

    border-color:
        var(--primary);

    background:
        white;

    box-shadow:
        0 0 0 3px
        rgba(
            37,
            99,
            235,
            0.10
        );
}


.filter-buttons {

    display:
        flex;

    gap:
        7px;
}


.filter-button {

    height:
        42px;

    border:
        none;

    border-radius:
        8px;

    padding:
        0 15px;

    background:
        var(--primary);

    color:
        white;

    font-weight:
        bold;

    font-size:
        12px;

    cursor:
        pointer;

    transition:
        0.2s;
}


.filter-button:hover {

    background:
        var(--primary-dark);
}


.clear-button {

    height:
        42px;

    display:
        inline-flex;

    align-items:
        center;

    padding:
        0 14px;

    border-radius:
        8px;

    background:
        #f3f3f3;

    color:
        #505050;

    text-decoration:
        none;

    font-size:
        12px;

    font-weight:
        bold;
}


.clear-button:hover {

    background:
        #e3e3e3;
}


/* =========================
   SALES PANEL
========================= */

.panel {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        14px;

    box-shadow:
        0 4px 16px
        rgba(
            15,
            23,
            42,
            0.04
        );

    overflow:
        hidden;
}


.panel-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid
        var(--border);

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;
}


.panel-header h2 {

    font-size:
        17px;

    margin-bottom:
        3px;
}


.panel-header p {

    color:
        var(--muted);

    font-size:
        11px;
}


.result-count {

    padding:
        6px 10px;

    border-radius:
        20px;

    color:
        #000000;

    background:
        #eeeeee;

    font-size:
        11px;

    font-weight:
        bold;
}


/* =========================
   TABLE
========================= */

.table-responsive {

    width:
        100%;

    overflow-x:
        auto;
}


table {

    width:
        100%;

    min-width:
        900px;

    border-collapse:
        collapse;
}


th {

    padding:
        13px 12px;

    text-align:
        left;

    background:
        #fafafa;

    color:
        var(--muted);

    border-bottom:
        1px solid
        var(--border);

    font-size:
        10px;

    text-transform:
        uppercase;

    letter-spacing:
        0.4px;
}


td {

    padding:
        14px 12px;

    border-bottom:
        1px solid
        #f3f3f3;

    font-size:
        12px;

    vertical-align:
        middle;
}


tbody tr {

    transition:
        0.15s;
}


tbody tr:hover {

    background:
        #fafafa;
}


.transaction {

    font-weight:
        bold;

    color:
        var(--text);
}


.cashier {

    font-weight:
        bold;
}


.date {

    color:
        var(--muted);

    white-space:
        nowrap;
}


.amount {

    font-weight:
        bold;
}


.profit {

    color:
        var(--green);

    font-weight:
        bold;
}


/* =========================
   ACTION BUTTONS
========================= */

.actions {

    display:
        flex;

    align-items:
        center;

    gap:
        6px;

    white-space:
        nowrap;
}


.action-button {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        7px 9px;

    border-radius:
        6px;

    text-decoration:
        none;

    font-size:
        10px;

    font-weight:
        bold;

    transition:
        0.2s;
}


.view-button {

    background:
        #eeeeee;

    color:
        #000000;
}


.view-button:hover {

    background:
        #dddddd;
}


.receipt-button {

    background:
        #eeeeee;

    color:
        #222222;
}


.receipt-button:hover {

    background:
        #dddddd;
}


/* =========================
   EMPTY
========================= */

.empty {

    text-align:
        center;

    color:
        var(--muted);

    padding:
        35px !important;
}


/* =========================
   RESPONSIVE
========================= */

@media (
    max-width: 1000px
) {

    .filter-form {

        grid-template-columns:
            repeat(
                2,
                1fr
            );
    }
}


@media (
    max-width: 800px
) {

    .navbar {

        padding:
            14px 16px;

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .nav-right {

        width:
            100%;

        justify-content:
            space-between;
    }


    .nav-links {

        flex-wrap:
            wrap;
    }


    .user-info {

        display:
            none;
    }


    .summary-grid,
    .summary-grid.staff {

        grid-template-columns:
            1fr;
    }


    .page-header {

        flex-direction:
            column;

        align-items:
            flex-start;
    }
}


@media (
    max-width: 600px
) {

    .container {

        padding:
            20px 13px;
    }


    .filter-form {

        grid-template-columns:
            1fr;
    }


    .filter-buttons {

        width:
            100%;
    }


    .filter-button,
    .clear-button {

        flex:
            1;

        justify-content:
            center;
    }


    .page-header h1 {

        font-size:
            23px;
    }

}

</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/admin_shell.css?v=4">
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">

<style>
/* ===== SALES HISTORY DARK AURORA OVERRIDE ===== */
:root {
    --history-surface: #262740;
    --history-surface-2: #202036;
    --history-surface-3: #18192d;
    --history-text: #ffffff;
    --history-muted: #b8bdd6;
    --history-border: #414366;
    --history-violet: #c06ceb;
    --history-mint: #5df09d;
}

/* page text */
body,
.container,
.page-header h1,
.summary-card h2,
.filter-title h2,
.panel-header h2,
.transaction,
.cashier,
.amount,
.profit,
td {
    color: var(--history-text) !important;
}

.page-header p,
.summary-label,
.filter-title p,
.panel-header p,
.date,
.empty {
    color: var(--history-muted) !important;
}

/* remove all default white page surfaces */
.summary-card,
.filter-panel,
.panel {
    background: var(--history-surface) !important;
    border-color: var(--history-border) !important;
    box-shadow: 0 14px 34px rgba(0,0,0,.24) !important;
}

.panel-header {
    background: rgba(32,32,54,.72) !important;
    border-bottom-color: var(--history-border) !important;
}

.summary-card {
    position: relative;
    overflow: hidden;
}
.summary-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--history-violet), var(--history-mint));
}

.summary-icon,
.summary-icon.blue,
.summary-icon.green,
.summary-icon.purple {
    background: linear-gradient(135deg, rgba(192,108,235,.22), rgba(93,240,157,.16)) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255,255,255,.12) !important;
}
.summary-icon .ui-icon { filter: brightness(0) invert(1) !important; }

/* buttons */
.back-button,
.clear-button,
.action-button,
.view-button,
.receipt-button,
.result-count {
    background: var(--history-surface-2) !important;
    color: #ffffff !important;
    border: 1px solid var(--history-border) !important;
}

.back-button:hover,
.clear-button:hover,
.action-button:hover,
.view-button:hover,
.receipt-button:hover {
    background: linear-gradient(135deg, rgba(192,108,235,.24), rgba(93,240,157,.16)) !important;
    color: #ffffff !important;
    border-color: rgba(255,255,255,.18) !important;
}

.action-button .ui-icon,
.view-button .ui-icon,
.receipt-button .ui-icon {
    filter: brightness(0) invert(1) !important;
}

.filter-button {
    background: linear-gradient(135deg, var(--history-violet), var(--history-mint)) !important;
    color: #17182b !important;
    border: 0 !important;
}

/* form controls */
.form-group label { color: #ffffff !important; }
.form-group input {
    background: var(--history-surface-2) !important;
    color: #ffffff !important;
    border-color: var(--history-border) !important;
}
.form-group input::placeholder { color: #8f94b1 !important; }
.form-group input:focus {
    background: var(--history-surface-2) !important;
    color: #ffffff !important;
    border-color: var(--history-mint) !important;
    box-shadow: 0 0 0 3px rgba(93,240,157,.12) !important;
}
input[type="date"] { color-scheme: dark; }

/* transaction table */
.table-responsive,
table,
thead,
tbody,
tr { background: transparent !important; }

th {
    background: var(--history-surface-2) !important;
    color: #c7cbe0 !important;
    border-bottom-color: var(--history-border) !important;
}

td {
    background: transparent !important;
    border-bottom-color: rgba(255,255,255,.08) !important;
}

tbody tr:hover { background: rgba(192,108,235,.08) !important; }

/* staff top navbar: no white active pill */
body:not(.admin-layout) .navbar {
    background: rgba(12,14,27,.94) !important;
    border-bottom: 1px solid rgba(255,255,255,.08) !important;
}
body:not(.admin-layout) .nav-links a { color: #d9dcef !important; }
body:not(.admin-layout) .nav-links a:hover {
    background: rgba(255,255,255,.07) !important;
    color: #ffffff !important;
}
body:not(.admin-layout) .nav-links .active {
    background: linear-gradient(135deg, rgba(192,108,235,.32), rgba(93,240,157,.22)) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255,255,255,.12) !important;
}
body:not(.admin-layout) .brand-icon {
    background: linear-gradient(135deg, rgba(192,108,235,.24), rgba(93,240,157,.16)) !important;
    border-color: rgba(255,255,255,.12) !important;
}
body:not(.admin-layout) .brand-icon .ui-icon { filter: brightness(0) invert(1) !important; }
body:not(.admin-layout) .user-info {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
}
body:not(.admin-layout) .user-info strong { color: #ffffff !important; }
body:not(.admin-layout) .user-info span { color: #aeb3cc !important; }


/* =========================================================
   ADMIN HISTORY FINAL OVERRIDES
   These selectors intentionally beat admin_shell.css rules.
========================================================= */
body.admin-layout section.filter-panel {
    background: #262740 !important;
    color: #ffffff !important;
    border: 1px solid #414366 !important;
    box-shadow: 0 14px 34px rgba(0,0,0,.24) !important;
}

body.admin-layout section.filter-panel .filter-title,
body.admin-layout section.filter-panel form.filter-form,
body.admin-layout section.filter-panel .form-group,
body.admin-layout section.filter-panel .filter-buttons {
    background: transparent !important;
}

body.admin-layout section.filter-panel .filter-title h2,
body.admin-layout section.filter-panel .form-group label {
    color: #ffffff !important;
}

body.admin-layout section.filter-panel .filter-title p {
    color: #b8bdd6 !important;
}

body.admin-layout section.filter-panel input {
    background: #202036 !important;
    color: #ffffff !important;
    border: 1px solid #414366 !important;
}

body.admin-layout section.filter-panel input::placeholder {
    color: #8f94b1 !important;
}

body.admin-layout section.filter-panel input:focus {
    background: #202036 !important;
    color: #ffffff !important;
    border-color: #5df09d !important;
    box-shadow: 0 0 0 3px rgba(93,240,157,.12) !important;
}

body.admin-layout section.filter-panel .clear-button {
    background: #202036 !important;
    color: #ffffff !important;
    border: 1px solid #414366 !important;
}

body.admin-layout section.panel {
    background: #262740 !important;
    color: #ffffff !important;
    border: 1px solid #414366 !important;
    box-shadow: 0 14px 34px rgba(0,0,0,.24) !important;
}

body.admin-layout section.panel .panel-header {
    background: #262740 !important;
    color: #ffffff !important;
    border-bottom: 1px solid #414366 !important;
}

body.admin-layout section.panel .panel-header h2 {
    color: #ffffff !important;
}

body.admin-layout section.panel .panel-header p {
    color: #b8bdd6 !important;
}

body.admin-layout section.panel .result-count {
    background: #202036 !important;
    color: #ffffff !important;
    border: 1px solid #414366 !important;
}

body.admin-layout section.panel .table-responsive {
    background: #262740 !important;
    border: 0 !important;
    box-shadow: none !important;
}

body.admin-layout section.panel table {
    background: #262740 !important;
    color: #ffffff !important;
}

body.admin-layout section.panel table thead,
body.admin-layout section.panel table tbody,
body.admin-layout section.panel table tr {
    background: transparent !important;
}

body.admin-layout section.panel table th {
    background: #202036 !important;
    color: #c7cbe0 !important;
    border-bottom: 1px solid #414366 !important;
}

body.admin-layout section.panel table td {
    background: #262740 !important;
    color: #ffffff !important;
    border-bottom: 1px solid rgba(255,255,255,.08) !important;
}

body.admin-layout section.panel table tbody tr:hover td {
    background: #2d2f4d !important;
    color: #ffffff !important;
}

body.admin-layout section.panel .transaction,
body.admin-layout section.panel .cashier,
body.admin-layout section.panel .amount,
body.admin-layout section.panel .profit {
    color: #ffffff !important;
}

body.admin-layout section.panel .date,
body.admin-layout section.panel .empty {
    color: #b8bdd6 !important;
}

body.admin-layout section.panel .view-button,
body.admin-layout section.panel .receipt-button,
body.admin-layout section.panel .action-button {
    background: #202036 !important;
    color: #ffffff !important;
    border: 1px solid #414366 !important;
}

body.admin-layout section.panel .view-button:hover,
body.admin-layout section.panel .receipt-button:hover,
body.admin-layout section.panel .action-button:hover {
    background: linear-gradient(135deg, rgba(192,108,235,.26), rgba(93,240,157,.18)) !important;
    color: #ffffff !important;
}

/* keep printing readable */
@media print {
    body { background: #ffffff !important; color: #111111 !important; }
    .summary-card,.filter-panel,.panel,th,td {
        background: #ffffff !important;
        color: #111111 !important;
        box-shadow: none !important;
    }
}
</style>

</head>


<body class="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin-layout' : ''; ?>">

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { include $_SERVER['DOCUMENT_ROOT'] . '/smart_inventory/includes/admin_sidebar.php'; } ?>


<!-- =========================
     NAVBAR
========================= -->

<header class="navbar">


    <div class="brand">


        <div class="brand-icon">
            <img class="ui-icon" src="/smart_inventory/assets/icons/history.svg" alt="">
        </div>


        <div>

            <h2>
                Smart Inventory
            </h2>

            <small>
                Sales History
            </small>

        </div>


    </div>


    <div class="nav-right">


        <div class="user-info">

            <strong>

                <?php

                echo htmlspecialchars(
                    $user_name
                );

                ?>

            </strong>


            <span>

                <?php

                echo htmlspecialchars(
                    $user_role
                );

                ?>

            </span>

        </div>


        <nav class="nav-links">


            <a
                href="<?php
                echo htmlspecialchars(
                    $dashboardUrl
                );
                ?>"
            >
                Dashboard
            </a>


            <a
                href="/smart_inventory/sales/sales.php"
            >
                Sales / POS
            </a>


            <a
                href="/smart_inventory/sales/history.php"
                class="active"
            >
                Sales History
            </a>


            <?php if (
                $user_role === 'admin'
            ): ?>


                <a
                    href="/smart_inventory/analytics/index.php"
                >
                    Analytics
                </a>


            <?php endif; ?>


            <?php if ($user_role === 'staff'): ?>
                <a href="/smart_inventory/staff/account.php">Account</a>
            <?php endif; ?>


            <a
                href="/smart_inventory/logout.php"
                class="logout"
            >
                Logout
            </a>


        </nav>


    </div>


</header>


<!-- =========================
     MAIN
========================= -->

<main class="container">


<!-- =========================
     PAGE HEADER
========================= -->

<section class="page-header">


    <div>


        <h1>
            Sales History
        </h1>


        <p>

            <?php if (
                $user_role === 'staff'
            ): ?>

                View and search your completed
                sales transactions.

            <?php else: ?>

                View and manage all completed
                sales transactions.

            <?php endif; ?>

        </p>


    </div>


    <a
        href="/smart_inventory/sales/sales.php"
        class="back-button"
    >
        ← Back to Sales
    </a>


</section>


<!-- =========================
     SUMMARY
========================= -->

<section
    class="summary-grid <?php
    echo
        $user_role === 'staff'
        ? 'staff'
        : '';
    ?>"
>


<!-- TRANSACTIONS -->

<div class="summary-card">


    <div class="summary-top">

        <span class="summary-label">
            Transactions Found
        </span>

        <div class="summary-icon blue">
            <img class="ui-icon" src="/smart_inventory/assets/icons/receipt.svg" alt="">
        </div>

    </div>


    <h2>

        <?php

        echo number_format(
            $totalTransactions
        );

        ?>

    </h2>


</div>


<!-- SALES -->

<div class="summary-card">


    <div class="summary-top">

        <span class="summary-label">

            <?php

            echo
                $user_role === 'staff'
                ? 'My Sales Total'
                : 'Sales Total';

            ?>

        </span>


        <div class="summary-icon green">
            <img class="ui-icon" src="/smart_inventory/assets/icons/money.svg" alt="">
        </div>

    </div>


    <h2>

        ₱<?php

        echo number_format(
            $totalResultSales,
            2
        );

        ?>

    </h2>


</div>


<!-- PROFIT ADMIN ONLY -->

<?php if (
    $user_role === 'admin'
): ?>


<div class="summary-card">


    <div class="summary-top">

        <span class="summary-label">
            Profit
        </span>


        <div class="summary-icon purple">
            <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt="">
        </div>

    </div>


    <h2>

        ₱<?php

        echo number_format(
            $totalResultProfit,
            2
        );

        ?>

    </h2>


</div>


<?php endif; ?>


</section>


<!-- =========================
     SEARCH AND FILTER
========================= -->

<section class="filter-panel">


    <div class="filter-title">

        <h2>
            <img class="ui-icon" src="/smart_inventory/assets/icons/search.svg" alt=""> Search & Filter
        </h2>

        <p>
            Search transactions or select a date range.
        </p>

    </div>


    <form
        method="GET"
        class="filter-form"
    >


        <!-- SEARCH -->

        <div class="form-group">

            <label for="search">
                Transaction Number
            </label>


            <input
                type="text"
                id="search"
                name="search"
                placeholder="Example: SALE-20260909..."
                value="<?php
                echo htmlspecialchars(
                    $search
                );
                ?>"
            >

        </div>


        <!-- START DATE -->

        <div class="form-group">

            <label for="start_date">
                Start Date
            </label>


            <input
                type="date"
                id="start_date"
                name="start_date"
                value="<?php
                echo htmlspecialchars(
                    $start_date
                );
                ?>"
            >

        </div>


        <!-- END DATE -->

        <div class="form-group">

            <label for="end_date">
                End Date
            </label>


            <input
                type="date"
                id="end_date"
                name="end_date"
                value="<?php
                echo htmlspecialchars(
                    $end_date
                );
                ?>"
            >

        </div>


        <div class="filter-buttons">


            <button
                type="submit"
                class="filter-button"
            >
                <img class="ui-icon" src="/smart_inventory/assets/icons/search.svg" alt=""> Filter
            </button>


            <a
                href="/smart_inventory/sales/history.php"
                class="clear-button"
            >
                Clear
            </a>


        </div>


    </form>


</section>


<!-- =========================
     SALES TABLE
========================= -->

<section class="panel">


<div class="panel-header">


    <div>

        <h2>
            <img class="ui-icon" src="/smart_inventory/assets/icons/receipt.svg" alt=""> Transactions
        </h2>

        <p>

            <?php

            if (
                $user_role === 'staff'
            ) {

                echo
                    "Only transactions processed by your account are shown.";

            } else {

                echo
                    "All transactions processed in the system.";

            }

            ?>

        </p>

    </div>


    <span class="result-count">

        <?php

        echo number_format(
            $totalTransactions
        );

        ?>

        result<?php

        echo
            $totalTransactions != 1
            ? 's'
            : '';

        ?>

    </span>


</div>


<div class="table-responsive">


<table>


<thead>


<tr>


    <th>
        Transaction
    </th>


    <?php if (
        $user_role === 'admin'
    ): ?>

        <th>
            Cashier
        </th>

    <?php endif; ?>


    <th>
        Date
    </th>


    <th>
        Total Amount
    </th>


    <?php if (
        $user_role === 'admin'
    ): ?>


        <th>
            Total Cost
        </th>


        <th>
            Profit
        </th>


    <?php endif; ?>


    <th>
        Action
    </th>


</tr>


</thead>


<tbody>


<?php if (
    count(
        $salesRows
    ) > 0
): ?>


<?php foreach (
    $salesRows
    as $sale
): ?>


<tr>


<!-- TRANSACTION -->

<td class="transaction">

    <?php

    echo htmlspecialchars(
        $sale[
            'transaction_number'
        ]
    );

    ?>

</td>


<!-- CASHIER ADMIN ONLY -->

<?php if (
    $user_role === 'admin'
): ?>


<td class="cashier">

    <?php

    echo htmlspecialchars(
        $sale[
            'cashier_name'
        ]
        ?? 'Unknown User'
    );

    ?>

</td>


<?php endif; ?>


<!-- DATE -->

<td class="date">

    <?php

    echo date(
        "M d, Y h:i A",
        strtotime(
            $sale[
                'sale_date'
            ]
        )
    );

    ?>

</td>


<!-- TOTAL -->

<td class="amount">

    ₱<?php

    echo number_format(
        $sale[
            'total_amount'
        ],
        2
    );

    ?>

</td>


<!-- ADMIN COST -->

<?php if (
    $user_role === 'admin'
): ?>


<td>

    ₱<?php

    echo number_format(
        $sale[
            'total_cost'
        ],
        2
    );

    ?>

</td>


<!-- ADMIN PROFIT -->

<td class="profit">

    ₱<?php

    echo number_format(
        $sale[
            'total_profit'
        ],
        2
    );

    ?>

</td>


<?php endif; ?>


<!-- ACTIONS -->

<td>


    <div class="actions">


        <a
            href="/smart_inventory/sales/view.php?id=<?php
            echo intval(
                $sale['id']
            );
            ?>"
            class="
                action-button
                view-button
            "
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/eye.svg" alt=""> View
        </a>


        <a
            href="/smart_inventory/sales/receipt.php?id=<?php
            echo intval(
                $sale['id']
            );
            ?>"
            class="
                action-button
                receipt-button
            "
        >
            <img class="ui-icon" src="/smart_inventory/assets/icons/print.svg" alt=""> Receipt
        </a>


    </div>


</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>


<td
    colspan="<?php
        echo
            $user_role === 'admin'
            ? '7'
            : '4';
    ?>"
    class="empty"
>

    No sales transactions
    matched your search.

</td>


</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</section>


</main>


</body>

</html>
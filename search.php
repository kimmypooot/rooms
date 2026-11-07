<?php
require_once __DIR__ . '/../includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>CSC RO VIII - ROOMS</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <?php include 'vendor_css.html' ?>
    <?= csrfMeta() ?>
</head>

<body>
<?php
imis_include('header');
include 'inc/sidebar.php';
?>

<main id="main" class="main">
    <div class="container-fluid px-4 py-2">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0" style="font-size: var(--otrs-text-sm);">
                <li class="breadcrumb-item"><a href="index">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Search</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="pagetitle otrs-ph">
            <div class="d-flex align-items-center gap-2">
                <div class="otrs-ph-icon icon-blue"><i class="bi bi-search"></i></div>
                <div>
                    <h1>Search Issuances</h1>
                    <p class="text-muted mb-0" style="font-size: var(--otrs-text-sm);">
                        <i class="bi bi-info-circle me-1"></i>Search by document name across every category and year
                    </p>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body bg-light shadow-sm p-4">
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Type at least 2 characters to search by document name&hellip;" autofocus>
                </div>
                <div class="form-text mt-2">
                    <i class="bi bi-info-circle me-1"></i>Searches document names only. Results are limited to the 100 most recent matches.
                </div>
            </div>
        </div>

        <div id="searchResultsWrap">
            <div class="otrs-alert otrs-alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>Start typing above to search issuances.</div>
            </div>
        </div>
    </div>
</main>

    <!---------------------------------------------------- Scripts Here ---------------------------------------------------->
    <?php include 'vendor_js.html' ?>
    <script>
        <?php include 'js/rood_search.js' ?>
    </script>

    <!---------------------------------------------------- Footer ---------------------------------------------------->
    <?php include 'inc/footer.php' ?>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up-short"></i>
    </a>
</body>

</html>

<?php
// Auth is already enforced upstream by includes/bootstrap.php (required by
// every page that includes this sidebar), which guarantees $_SESSION['username']
// is set before this file ever runs — no separate session/auth check needed here.
$userId = $_SESSION['username'] ?? '';
?>

<aside id="sidebar" class="sidebar bg-light border-end shadow-sm">
    <div class="d-flex flex-column h-100">
        <!-- Sidebar Header (Optional) -->
        <div class="px-3 py-2 border-bottom">
            <h5 class="mb-0 text-primary fw-bold">Navigation</h5>
        </div>

        <!-- Sidebar Navigation -->
        <nav class="flex-grow-1 overflow-auto py-3">
            <ul class="nav flex-column px-3 gap-2" id="sidebar-nav">

                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center rounded-3 py-3 px-3 text-dark bg-white shadow-sm border" href="index">
                        <i class="bi bi-speedometer2 me-3 fs-5 text-primary"></i>
                        <span class="fw-semibold">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center rounded-3 py-3 px-3 text-dark bg-white shadow-sm border" href="search">
                        <i class="bi bi-search me-3 fs-5 text-primary"></i>
                        <span class="fw-semibold">Search Issuances</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex align-items-start rounded-3 py-3 px-3 text-dark bg-white shadow-sm border" href="competency_based_recruitment_and_promotion_issuances" data-ref="1">
                        <i class="bi bi-briefcase me-3 fs-5 text-success mt-1"></i>
                        <span class="fw-medium">Competency-Based Recruitment and Promotion Issuances</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex align-items-start rounded-3 py-3 px-3 text-dark bg-white shadow-sm border" href="policies_awa" data-ref="2">
                        <i class="bi bi-house-door me-3 fs-5 text-info mt-1"></i>
                        <span class="fw-medium">Policies on Alternative Work Arrangements (AWA)</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex align-items-start rounded-3 py-3 px-3 text-dark bg-white shadow-sm border" href="policies_on_attendance_and_leave_administration" data-ref="3">
                        <i class="bi bi-calendar-check me-3 fs-5 text-warning mt-1"></i>
                        <span class="fw-medium">Policies on Attendance and Leave Administration</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex align-items-start rounded-3 py-3 px-3 text-dark bg-white shadow-sm border" href="regional_office_memorandum" data-ref="4">
                        <i class="bi bi-file-earmark-text me-3 fs-5 text-danger mt-1"></i>
                        <span class="fw-medium">Regional Office Memorandum</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex align-items-start rounded-3 py-3 px-3 text-dark bg-white shadow-sm border" href="regional_office_orders" data-ref="5">
                        <i class="bi bi-file-earmark-ruled me-3 fs-5 text-secondary mt-1"></i>
                        <span class="fw-medium">Regional Office Orders</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex align-items-start rounded-3 py-3 px-3 text-dark bg-white shadow-sm border" href="training_related_issuances" data-ref="4">
                        <i class="bi bi-book me-3 fs-5 text-primary mt-1"></i>
                        <span class="fw-medium">Training Related Issuances</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex align-items-start rounded-3 py-3 px-3 text-dark bg-white shadow-sm border" href="welfare_fund_related_issuances" data-ref="5">
                        <i class="bi bi-heart me-3 fs-5 text-danger mt-1"></i>
                        <span class="fw-medium">Welfare Fund and Employees' Benefits Related Issuances</span>
                    </a>
                </li>

            </ul>
        </nav>

        <!-- Optional: User Profile Section at Bottom -->
        <div class="px-3 py-3 border-top bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                    <i class="bi bi-person-circle fs-4 text-primary"></i>
                </div>
                <div class="flex-grow-1">
                    <p class="mb-0 fw-semibold small text-truncate"><?php echo htmlspecialchars($userId); ?></p>
                    <p class="mb-0 text-muted small">Active</p>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Add this JavaScript for active state management -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop().split('.')[0];
    const navLinks = document.querySelectorAll('#sidebar-nav .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('bg-primary', 'bg-opacity-10', 'border-primary', 'border-2');
            link.classList.remove('bg-white');
        }
        
        // Hover effects
        link.addEventListener('mouseenter', function() {
            if (!this.classList.contains('bg-primary')) {
                this.classList.add('bg-light');
                this.classList.remove('bg-white');
            }
        });
        
        link.addEventListener('mouseleave', function() {
            if (!this.classList.contains('bg-primary')) {
                this.classList.add('bg-white');
                this.classList.remove('bg-light');
            }
        });
    });
});
</script>
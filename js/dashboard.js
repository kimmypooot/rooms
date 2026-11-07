//<!--------------------------------------- Submit Sample Data --------------------------------------->
$(document).ready(function () {
    $("#submitFormBtn").click(function (e) {
        e.preventDefault();

        $.ajax({
            //File Naming follow this - ../backend/insert/ - file location, file naming - process_process-name_file-reference.php
            url: "backend/insert/insert_sample-data_dashboard.php", // PHP file to handle the request
            type: "POST",
            data: $("#sampleForm").serialize(),
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        title: "Success!",
                        text: response.message,
                        icon: "success",
                        confirmButtonText: "OK"
                    }).then(() => {
                        $("#sampleForm")[0].reset(); // Reset the form
                        $("#sampleModal").modal("hide"); // Hide the modal
                        $('#sampleTable').DataTable().ajax.reload();
                    });
                } else {
                    Swal.fire({
                        title: "Error!",
                        text: response.message,
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                }
            },
            error: function () {
                Swal.fire({
                    title: "Error!",
                    text: "Something went wrong. Please try again.",
                    icon: "error",
                    confirmButtonText: "OK"
                });
            }
        });
    });
});

//<!--------------------------------------- Fetch DataTables --------------------------------------->
$(document).ready(function () {
    $('#sampleTable').DataTable({
        "processing": true, // Show loading indicator
        "serverSide": false, // Disable server-side processing
        "ajax": {
            "url": "backend/fetch/fetch_sample-data_dashboard.php",
            "type": "GET", // Use GET since we're only fetching data
            "dataSrc": "data"
        },
        "columns": [
            { 
                "data": null, 
                "render": function (data, type, row, meta) {
                    return meta.row + 1; // Generates a dynamic row number
                } 
            },
            { "data": "name" },
            { "data": "age" },
            { 
                "data": "status",
                "render": function (data, type, row) {
                    return formatStatus(row.status); // Call function to format the status
                }
            },
            { 
                "data": null,
                "render": function (data, type, row) {
                    return `<button class="btn btn-sm btn-primary edit-btn" data-id="${row.id}"><i class="bi bi-pencil-square"></i> Edit</button>
                            <button class="btn btn-sm btn-danger delete-btn" data-id="${row.id}"><i class="bi bi-trash"></i> Delete</button>`;
                }
            }
        ]
    });

    function formatStatus(status) {
        let badgeClass, icon;
        
        switch (status) {
            case "Pending":
                badgeClass = "badge bg-warning text-dark";
                icon = "<i class='bi bi-hourglass-split'></i>"; // Clock icon
                break;
            case "Accepted":
                badgeClass = "badge bg-success";
                icon = "<i class='bi bi-check-circle-fill'></i>"; // Check icon
                break;
            case "Rejected":
                badgeClass = "badge bg-danger";
                icon = "<i class='bi bi-x-circle-fill'></i>"; // Cross icon
                break;
            default:
                badgeClass = "badge bg-secondary";
                icon = "❔"; // Question mark icon
                break;
        }
        
        return `<span class="${badgeClass} fs-6">${icon} ${status}</span>`;
    }
});

//<!--------------------------------------- Fetch Data to be Edited --------------------------------------->
$(document).on('click', '.edit-btn', function () {
    let id = $(this).data('id'); // Get the data-id from the button

    $.ajax({
        url: 'backend/fetch/fetch_edit-sample-data_dashboard.php', // Backend script to fetch data
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                $('#editModal').attr('data-id', id); // Store ID in modal
                $('#nameEdit').val(response.data.name);  // Populate name field
                $('#ageEdit').val(response.data.age);    // Populate age field
                $('#statusEdit').val(response.data.status); // Populate status field
                $('#editModal').modal('show'); // Show modal
            } else {
                alert('Error fetching data.');
            }
        },
        error: function () {
            alert('Failed to fetch data.');
        }
    });
});

//<!--------------------------------------- Edit Data --------------------------------------->
$(document).ready(function () {
    $("#submitEditFormBtn").click(function (e) {
        e.preventDefault();
        let id = $('#editModal').attr('data-i');
        $.ajax({
            //File Naming follow this - ../backend/insert/ - file location, file naming - process_process-name_file-reference.php
            url: "backend/update/update_sample-data_dashboard.php", // PHP file to handle the request
            type: "POST",
            data: $("#editSampleForm").serialize() + "&id=" + id,
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        title: "Success!",
                        text: response.message,
                        icon: "success",
                        confirmButtonText: "OK"
                    }).then(() => {
                        $('#sampleTable').DataTable().ajax.reload();
                    });
                } else {
                    Swal.fire({
                        title: "Error!",
                        text: response.message,
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                }
            },
            error: function () {
                Swal.fire({
                    title: "Error!",
                    text: "Something went wrong. Please try again.",
                    icon: "error",
                    confirmButtonText: "OK"
                });
            }
        });
    });
});

//<!--------------------------------------- Delete Data --------------------------------------->
$(document).on('click', '.delete-btn', function () {
    let id = $(this).data('id'); // Get the ID from the button

    Swal.fire({
        title: "Are you sure?",
        text: "This action cannot be undone!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {
            // Send AJAX request to delete record
            $.ajax({
                url: "backend/delete/delete_sample-data_dashboard.php", // Your backend script
                type: "POST",
                data: { id: id },
                dataType: "json",
                success: function (response) {
                    if (response.success) {
                        Swal.fire("Deleted!", "The record has been deleted.", "success");
                        $('#sampleTable').DataTable().ajax.reload(); // Refresh table
                    } else {
                        Swal.fire("Error!", "Failed to delete record.", "error");
                    }
                },
                error: function () {
                    Swal.fire("Error!", "An unexpected error occurred.", "error");
                }
            });
        }
    });
});


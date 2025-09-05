jQuery(document).ready(function($) {
    
    // کدهای مربوط به فیلدساز و تب‌های تنظیمات (بدون تغییر)
    // ...
    initializeFieldBuilder();
    initializeSettingsTabs();

    // **بخش جدید: منطق تب‌های صفحه ورودی‌ها و گزارش‌گیری**
    initializeEntriesPage();


    function initializeFieldBuilder() {
        // ... (تمام کدهای این تابع بدون تغییر باقی می‌ماند)
    }

    function initializeSettingsTabs() {
        // ... (تمام کدهای این تابع بدون تغییر باقی می‌ماند)
    }

    /**
     * **تابع جدید: مدیریت تعاملات صفحه ورودی‌ها**
     */
    function initializeEntriesPage() {
        var $wrapper = $('.nfb-entries-tabs-wrapper');
        if (!$wrapper.length) return;

        var currentChart = null; // برای نگهداری نمونه نمودار فعال

        // مدیریت تب‌ها (لیست ورودی / گزارش‌ها)
        $wrapper.find('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab');

            $wrapper.find('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $wrapper.find('.nfb-tab-pane').removeClass('active');
            $('#tab-content-' + tabId).addClass('active');
        });

        // مدیریت انتخاب فیلد برای گزارش‌گیری
        $('#nfb-reports-field-selector').on('change', function() {
            var field_key = $(this).val();
            var form_id = $('.nfb-reports-container').data('form-id');
            var $chartWrapper = $('.nfb-chart-wrapper');

            if (!field_key) {
                if (currentChart) {
                    currentChart.destroy();
                }
                $chartWrapper.hide();
                return;
            }

            $chartWrapper.show().addClass('loading');

            $.ajax({
                url: nfb_admin_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'nfb_get_chart_data',
                    nonce: nfb_admin_params.nonce,
                    form_id: form_id,
                    field_key: field_key,
                },
                success: function(response) {
                    if (response.success) {
                        renderReportChart(response.data);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred while fetching chart data.');
                },
                complete: function() {
                    $chartWrapper.removeClass('loading');
                }
            });
        });

        /**
         * رندر کردن نمودار با داده‌های دریافتی
         * @param {object} chartData - داده‌های شامل labels و data
         */
        function renderReportChart(chartData) {
            var ctx = document.getElementById('nfbReportChart').getContext('2d');
            
            // اگر نموداری از قبل وجود دارد، آن را از بین ببر
            if (currentChart) {
                currentChart.destroy();
            }

            currentChart = new Chart(ctx, {
                type: 'pie', // یا 'bar' بر اساس نوع داده
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'تعداد ورودی‌ها',
                        data: chartData.data,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 159, 64, 0.7)'
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'تحلیل داده‌های فیلد انتخاب شده'
                        }
                    }
                }
            });
        }
    }

});


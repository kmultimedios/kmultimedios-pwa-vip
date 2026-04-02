/**
 * JavaScript CORREGIDO para el panel de administración
 * Archivo: assets/admin.js
 */

jQuery(document).ready(function($) {
    // ✅ FUNCIÓN PARA ADVERTIR USUARIO (CORREGIDA)
    window.warnUser = function(userId) {
        if (!confirm('¿Estás seguro de enviar una advertencia a este usuario?')) {
            return;
        }
        
        // Mostrar indicador de carga
        const button = $('button[onclick="warnUser(' + userId + ')"]');
        const originalText = button.html();
        button.html('<i class="fa fa-spinner fa-spin"></i> Enviando...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'warn_user',
                user_id: userId,
                nonce: kmul_admin.nonce // Asegurar que el nonce esté disponible
            },
            success: function(response) {
                if (response.success) {
                    // ✅ ÉXITO
                    alert('✅ ' + response.data);
                    
                    // Cambiar el botón a "Advertido" temporalmente
                    button.html('<i class="fa fa-check"></i> Advertido')
                          .removeClass('btn-warning')
                          .addClass('btn-success')
                          .prop('disabled', true);
                          
                    // Recargar la página después de 2 segundos para actualizar datos
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    // ✅ ERROR DEL SERVIDOR
                    alert('❌ Error: ' + (response.data || 'Error desconocido'));
                    button.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                // ✅ ERROR DE CONEXIÓN
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                alert('❌ Error de conexión: ' + error);
                button.html(originalText).prop('disabled', false);
            }
        });
    };
    
    // ✅ FUNCIÓN PARA BLOQUEAR USUARIO
    window.blockUser = function(userId) {
        if (!confirm('¿Estás seguro de bloquear a este usuario?')) {
            return;
        }
        
        const button = $('button[onclick="blockUser(' + userId + ')"]');
        const originalText = button.html();
        button.html('<i class="fa fa-spinner fa-spin"></i> Procesando...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'block_user',
                user_id: userId,
                nonce: kmul_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Usuario bloqueado correctamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + response.data);
                    button.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Error de conexión');
                button.html(originalText).prop('disabled', false);
            }
        });
    };
    
    // ✅ FUNCIÓN PARA DESBLOQUEAR USUARIO
    window.unblockUser = function(userId) {
        if (!confirm('¿Estás seguro de desbloquear a este usuario?')) {
            return;
        }
        
        const button = $('button[onclick="unblockUser(' + userId + ')"]');
        const originalText = button.html();
        button.html('<i class="fa fa-spinner fa-spin"></i> Procesando...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'unblock_user',
                user_id: userId,
                nonce: kmul_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Usuario desbloqueado correctamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + response.data);
                    button.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Error de conexión');
                button.html(originalText).prop('disabled', false);
            }
        });
    };
    
    // ✅ FUNCIÓN PARA PROBAR EL SISTEMA DE EMAILS
    window.testWarningSystem = function() {
        if (!confirm('¿Enviar un email de prueba al administrador?')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_warning_system',
                nonce: kmul_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data);
                } else {
                    alert('❌ Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Error de conexión: ' + error);
            }
        });
    };
    
    // ✅ FILTROS Y BÚSQUEDA
    $('#user-filter, #risk-filter, #search-user').on('change keyup', function() {
        filterUsers();
    });
    
    function filterUsers() {
        const userFilter = $('#user-filter').val();
        const riskFilter = $('#risk-filter').val();
        const searchTerm = $('#search-user').val().toLowerCase();
        
        $('tbody tr').each(function() {
            const $row = $(this);
            const userName = $row.find('td:first').text().toLowerCase();
            const riskClass = $row.attr('class') || '';
            
            let showUser = true;
            
            // Filtrar por tipo de usuario
            if (userFilter !== 'all') {
                if (userFilter === 'infractors' && !riskClass.includes('risk-high')) showUser = false;
                if (userFilter === 'suspicious' && !riskClass.includes('risk-medium')) showUser = false;
                if (userFilter === 'normal' && !riskClass.includes('risk-low')) showUser = false;
                if (userFilter === 'blocked' && !$row.html().includes('BLOQUEADO')) showUser = false;
            }
            
            // Filtrar por nivel de riesgo
            if (riskFilter !== 'all') {
                if (!riskClass.includes('risk-' + riskFilter)) showUser = false;
            }
            
            // Filtrar por búsqueda
            if (searchTerm && !userName.includes(searchTerm)) {
                showUser = false;
            }
            
            $row.toggle(showUser);
        });
    }
    
    // ✅ TOOLTIPS
    $('[data-toggle="tooltip"]').tooltip();
    
    // ✅ ACORDEONES
    $('.accordion-header').click(function() {
        $(this).next('.accordion-content').slideToggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });
});

// ✅ ASEGURAR QUE LAS FUNCIONES ESTÉN DISPONIBLES GLOBALMENTE
window.jQuery = jQuery;
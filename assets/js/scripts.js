jQuery(document).ready(function($) {
    var dropArea = $('#drop-area');
    var step1 = $('#step1');
    var fileInput = $('#file-input');
    var analysisResult = $('.feedback-content');
    var spinnerContainer = $('#spinner-and-analyzing');
    var spinner = $('<div class="spinner"></div>');
    var loadingText = $('<div class="loading-text"></div>');
    var analysisContainer = $('#claude-analysis-result');
    var processingSteps = $('#loading-steps');
    var copyResult = $('#copyResult'); //COPY BUTTON
    
    analysisContainer.hide();
    processingSteps.hide();
    
    // Handle drag and drop events
    dropArea.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropArea.addClass('dragover');
    });

    dropArea.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropArea.removeClass('dragover');
    });

    dropArea.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropArea.removeClass('dragover');

        var files = e.originalEvent.dataTransfer.files;

        if (files.length > 0) {
            const file = files[0];
			processFile(file);
        }
    
    });

    fileInput.on('change', function(e) {
        if (fileInput[0].files.length > 0) {
            processFile(fileInput[0].files[0]);
        }
    });

    // COPY BUTTON CODE
    copyResult.on('click', function() {
        var tempTextarea = $('<textarea>');
        $('body').append(tempTextarea);
        tempTextarea.val(analysisResult.text()).select(); 
        document.execCommand('copy');  
        tempTextarea.remove(); 
        alert('Text copied to clipboard!');
    });
    
    // DOWNLOAD BUTTON CODE
    $('#downloadResult').on('click', function() {
        var contentToDownload = analysisResult.html();  // Get the HTML content to preserve formatting
        var header = '<html xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="UTF-8"></head><body>';
        var footer = '</body></html>';
        var content = header + contentToDownload + footer;

        var blob = new Blob([content], { type: 'application/msword' });

        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'analysis-result.doc';
        link.click();
    });
    
    // START NEW ANALYSIS BUTTON CODE
    $('#newAnalysisBtn').on('click', function() {
        // Hide analysis result section
        analysisContainer.hide();

        // Clear previous analysis content
        analysisResult.empty();

        // Reset the form and UI to the initial state
        step1.show();
        processingSteps.hide();
        $('#loading-steps p').css('opacity', 0.5);
    });
	
	function processFile(file) {
		const validationResult = validateFile(file);

        if (validationResult.isValid) { 
            handleFileUpload(file);
        }
	}
    
    function validateFile(file) {
        const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB in bytes
        const ALLOWED_TYPES = {
            'image/jpeg': true,
            'image/jpg': true,
            'image/png': true,
            'application/pdf': true
        };
        
        const MAX_WIDTH = 8000;   // Maximum allowed width in pixels
        const MAX_HEIGHT = 8000;  // Maximum allowed height in pixels

        // Validation result object
        let result = {
            isValid: true,
            error: ''
        };

        // Check file size
        if (file.size > MAX_FILE_SIZE) {
            result.isValid = false;
            result.error = 'File size must be under 10MB. Please compress your file or upload a smaller one.';
            alert('⚠️ Error: ' + result.error);
            return result;
        }

        // Check file type
        if (!ALLOWED_TYPES[file.type]) {
            result.isValid = false;
            result.error = 'Invalid file type. Please upload only JPG, PNG, or PDF files.';
            alert('⚠️ Error: ' + result.error);
            return result;
        }
        
        if (file.type.startsWith('image/')) { // Check image resolution
            const img = new Image();
            img.onload = function () {
                if (img.width > MAX_WIDTH || img.height > MAX_HEIGHT) {
                result.isValid = false;
                result.error = `Image resolution must be under ${MAX_WIDTH}x${MAX_HEIGHT} pixels.`;
                alert('⚠️ Error: ' + result.error);
                }
            };
             
            img.src = URL.createObjectURL(file);  // Load the image
        }
		

        return result;
    }

    
    function handleFileUpload(file) { 
        var formData = new FormData();
        formData.append('action', 'analyze_document');
        formData.append('document', file);
        formData.append('claude_nonce', ajax_object.nonce);

        // Add the spinner and loading text
        analysisResult.html('');
        analysisResult.show();
        spinnerContainer.append(spinner);
        spinner.show();
        spinnerContainer.append(loadingText.text('Analyzing your portfolio...'));
        processingSteps.show();
        $('#loading-steps p').css('opacity', 0.5);
        setTimeout(function() { $('#step-1').css('opacity', 1); }, 500);  // Step 1 after 0.5s
        setTimeout(function() { $('#step-2').css('opacity', 1); }, 1500); // Step 2 after 1.5s
        setTimeout(function() { $('#step-3').css('opacity', 1); }, 2500); // Step 3 after 2.5s
        
        spinner.fadeOut(0, function() {
            spinner.fadeIn(600);
        });
        
        step1.hide();
		
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                analysisResult.html('');
            },
            success: function(response) {
                if (response.success) {
                    analysisContainer.show();
                    $.post(ajax_object.ajax_url, {
                        action: 'store_analysis',
                        analysis: response.data,
                        claude_nonce: ajax_object.nonce
                    });
                    analysisResult.html(response.data);
                    analysisContainer.fadeOut(0, function() {
                        analysisContainer.fadeIn(1000);
                    });
                    spinner.hide();
                    loadingText.hide();
                    processingSteps.hide();
                    fileInput.val('');
                } else {
                    alert('⚠️ Analysis Error: ' + response.data);
                    
                }
            },
            error: function(xhr, status, error) {
                alert('⚠️ Analysis Error: ' + response.data);
                fileInput.val('');
            }
        });
    }
});

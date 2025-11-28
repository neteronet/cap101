<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antique Province Location Form</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            color: #333;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        select:hover {
            border-color: #667eea;
        }
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        select:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .loading {
            color: #667eea;
            font-style: italic;
            font-size: 13px;
            margin-top: 5px;
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 10px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        button:active {
            transform: translateY(0);
        }
        .result {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            display: none;
        }
        .result.show {
            display: block;
        }
        .result h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .result p {
            color: #555;
            line-height: 1.6;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📍 Antique Province Locator</h1>
        
        <form id="locationForm">
            <div class="form-group">
                <label for="province">Province</label>
                <select id="province" name="province">
                    <option value="">Loading...</option>
                </select>
                <div id="provinceLoading" class="loading" style="display:none;">Loading provinces...</div>
            </div>

            <div class="form-group">
                <label for="municipality">Municipality</label>
                <select id="municipality" name="municipality" disabled>
                    <option value="">Select province first</option>
                </select>
                <div id="municipalityLoading" class="loading" style="display:none;">Loading municipalities...</div>
            </div>

            <div class="form-group">
                <label for="barangay">Barangay</label>
                <select id="barangay" name="barangay" disabled>
                    <option value="">Select municipality first</option>
                </select>
                <div id="barangayLoading" class="loading" style="display:none;">Loading barangays...</div>
            </div>

            <button type="submit">Submit Location</button>
        </form>

        <div id="result" class="result">
            <h3>Selected Location:</h3>
            <p><strong>Province:</strong> <span id="selectedProvince"></span></p>
            <p><strong>Municipality:</strong> <span id="selectedMunicipality"></span></p>
            <p><strong>Barangay:</strong> <span id="selectedBarangay"></span></p>
        </div>
    </div>

    <script>
        const API_BASE = 'https://psgc.gitlab.io/api';
        const ANTIQUE_CODE = '060600000'; // Antique province code

        // Fetch and populate provinces (filtered to Antique)
        async function loadProvinces() {
            const select = document.getElementById('province');
            const loading = document.getElementById('provinceLoading');
            
            try {
                loading.style.display = 'block';
                const response = await fetch(`${API_BASE}/provinces/`);
                const provinces = await response.json();
                
                // Filter for Antique province
                const antique = provinces.find(p => p.code === ANTIQUE_CODE);
                
                select.innerHTML = '';
                if (antique) {
                    const option = document.createElement('option');
                    option.value = antique.code;
                    option.textContent = antique.name;
                    select.appendChild(option);
                    
                    // Auto-load municipalities for Antique
                    loadMunicipalities(antique.code);
                } else {
                    select.innerHTML = '<option value="">Antique not found</option>';
                }
            } catch (error) {
                console.error('Error loading provinces:', error);
                select.innerHTML = '<option value="">Error loading provinces</option>';
            } finally {
                loading.style.display = 'none';
            }
        }

        // Fetch and populate municipalities
        async function loadMunicipalities(provinceCode) {
            const select = document.getElementById('municipality');
            const loading = document.getElementById('municipalityLoading');
            
            try {
                loading.style.display = 'block';
                select.disabled = true;
                select.innerHTML = '<option value="">Loading...</option>';
                
                const response = await fetch(`${API_BASE}/provinces/${provinceCode}/municipalities/`);
                const municipalities = await response.json();
                
                select.innerHTML = '<option value="">-- Select Municipality --</option>';
                municipalities.forEach(mun => {
                    const option = document.createElement('option');
                    option.value = mun.code;
                    option.textContent = mun.name;
                    select.appendChild(option);
                });
                
                select.disabled = false;
            } catch (error) {
                console.error('Error loading municipalities:', error);
                select.innerHTML = '<option value="">Error loading municipalities</option>';
            } finally {
                loading.style.display = 'none';
            }
        }

        // Fetch and populate barangays
        async function loadBarangays(municipalityCode) {
            const select = document.getElementById('barangay');
            const loading = document.getElementById('barangayLoading');
            
            try {
                loading.style.display = 'block';
                select.disabled = true;
                select.innerHTML = '<option value="">Loading...</option>';
                
                const response = await fetch(`${API_BASE}/municipalities/${municipalityCode}/barangays/`);
                const barangays = await response.json();
                
                select.innerHTML = '<option value="">-- Select Barangay --</option>';
                barangays.forEach(brgy => {
                    const option = document.createElement('option');
                    option.value = brgy.code;
                    option.textContent = brgy.name;
                    select.appendChild(option);
                });
                
                select.disabled = false;
            } catch (error) {
                console.error('Error loading barangays:', error);
                select.innerHTML = '<option value="">Error loading barangays</option>';
            } finally {
                loading.style.display = 'none';
            }
        }

        // Event listeners
        document.getElementById('municipality').addEventListener('change', function() {
            const barangaySelect = document.getElementById('barangay');
            barangaySelect.innerHTML = '<option value="">Select municipality first</option>';
            barangaySelect.disabled = true;
            
            if (this.value) {
                loadBarangays(this.value);
            }
        });

        document.getElementById('locationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const province = document.getElementById('province');
            const municipality = document.getElementById('municipality');
            const barangay = document.getElementById('barangay');
            const result = document.getElementById('result');
            
            if (!municipality.value || !barangay.value) {
                alert('Please select all fields');
                return;
            }
            
            document.getElementById('selectedProvince').textContent = province.options[province.selectedIndex].text;
            document.getElementById('selectedMunicipality').textContent = municipality.options[municipality.selectedIndex].text;
            document.getElementById('selectedBarangay').textContent = barangay.options[barangay.selectedIndex].text;
            
            result.classList.add('show');
        });

        // Initialize on page load
        loadProvinces();
    </script>
</body>
</html>
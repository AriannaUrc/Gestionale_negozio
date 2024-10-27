<?php
// Path to the product file
$productFile = 'products.json'; // Path to products file


// Load existing products from the JSON file if it exists
$products = [];
$productIDCounter = 1; // To generate unique IDs

if (file_exists($productFile)) {
    $products = json_decode(file_get_contents($productFile), true);
    if (is_array($products)) {
        $productIDCounter = count($products) + 1; // Set counter based on existing products
    } else {
        $products = [];
    }
}

// Set the content type to JSON
header('Content-Type: application/json');

// Get the request method and URI
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$requestUriParts = explode('/', trim($requestUri, '/'));

// Log the request
logRequest($requestMethod, $requestUri);

// Routes handling prodotti
if($requestUriParts[2] != 'magazzino' && $requestUriParts[2] != 'ricerca_magazzino')
switch ($requestMethod) {
    case 'POST':
        if (count($requestUriParts) === 3 && $requestUriParts[2] === 'prodotto') {
            // Log the raw input for debugging
            $rawInput = file_get_contents('php://input');
            file_put_contents('input_log.json', $rawInput); // Log the input
    
            // Adding a new product
            $data = json_decode($rawInput, true);
            if (is_array($data) && isset($data['nome'], $data['tipo_prod'], $data['prezzo'], $data['vietato_min'])) {
                // Check for spaces in the product name and type
                if (strpos($data['nome'], ' ') !== false) {
                    echo json_encode(['error' => 'Product name cannot contain spaces']);
                    exit;
                }
                if (strpos($data['tipo_prod'], ' ') !== false) {
                    echo json_encode(['error' => 'Product type cannot contain spaces']);
                    exit;
                }
    
                $data['id'] = $productIDCounter; // Set the current counter as the ID
                $products[] = $data;
                saveProductsToFile($products); // Save products to file
                $productIDCounter++; // Increment the counter after saving
                echo json_encode($data); // Return the saved product data
            } else {
                echo json_encode(['error' => 'Invalid input']);
            }
        }
        break;
    
    case 'PUT':
        // Handle updating a specific product by ID
        if (count($requestUriParts) === 4 && $requestUriParts[2] === 'prodotto') {
            $productId = (int)$requestUriParts[3]; // Get the product ID from the URL
            $data = json_decode(file_get_contents('php://input'), true);
    
            // Check for spaces in the product name and type
            if (isset($data['nome']) && strpos($data['nome'], ' ') !== false) {
                echo json_encode(['error' => 'Product name cannot contain spaces']);
                exit;
            }
            if (isset($data['tipo_prod']) && strpos($data['tipo_prod'], ' ') !== false) {
                echo json_encode(['error' => 'Product type cannot contain spaces']);
                exit;
            }
    
            foreach ($products as &$product) {
                if ($product['id'] === $productId) {
                    // Update product details
                    $product = array_merge($product, $data);
                    saveProductsToFile($products); // Save updated products to file
                    echo json_encode($product);
                    exit;
                }
            }
            echo json_encode(['error' => 'Product not found']);
        } elseif (count($requestUriParts) === 5 && $requestUriParts[2] === 'prodotto' && $requestUriParts[4] === 'prezzo') {
            $productId = (int)$requestUriParts[3]; // Get the product ID from the URL
            $data = json_decode(file_get_contents('php://input'), true);
    
            // Check if 'prezzo' is provided in the request
            if (!isset($data['prezzo'])) {
                echo json_encode(['error' => 'Price must be specified']);
                exit;
            }
    
            foreach ($products as &$product) {
                if ($product['id'] === $productId) {
                    // Update the price
                    $product['prezzo'] = $data['prezzo'];
                    saveProductsToFile($products); // Save updated products to file
                    echo json_encode($product);
                    exit;
                }
            }
            echo json_encode(['error' => 'Product not found']);
        } else {
            echo json_encode(['error' => 'Invalid request']);
        }
        break;

    case 'GET':
        // Handle specific product by ID
        if (count($requestUriParts) === 4 && $requestUriParts[2] === 'prodotto') {
            $productId = (int)$requestUriParts[3]; // Get the product ID from the URL
            foreach ($products as $product) {
                if ($product['id'] === $productId) {
                    echo json_encode($product);
                    exit;
                }
            }
            echo json_encode(['error' => 'Product not found']);
        } 
        // Handle all products
        elseif (count($requestUriParts) === 3 && $requestUriParts[2] === 'prodotto') {
            echo json_encode($products);
        } 

        // Search for products by type
        elseif (count($requestUriParts) === 4 && $requestUriParts[2] === 'ricerca_tipologia_prodotto') {
            $productType = rawurldecode($requestUriParts[3]); // Decode URL parameter
            $foundProducts = array_filter($products, function($product) use ($productType) {
                return $product['tipo_prod'] === $productType;
            });
        
            if (!empty($foundProducts)) {
                echo json_encode(array_values($foundProducts));
            } else {
                echo json_encode(['error' => 'No products found for the specified type']);
            }
        }

        // Invalid request handling
        else {
            echo json_encode(['error' => 'Invalid request']);
        }
        break;

    case 'DELETE':
        // Handle deleting a specific product by ID
        if (count($requestUriParts) === 4 && $requestUriParts[2] === 'prodotto') {
            $productId = (int)$requestUriParts[3]; // Get the product ID from the URL
            foreach ($products as $index => $product) {
                if ($product['id'] === $productId) {
                    unset($products[$index]);
                    saveProductsToFile(array_values($products)); // Save updated products to file
                    echo json_encode(['message' => 'Product deleted']);
                    exit;
                }
            }
            echo json_encode(['error' => 'Product not found']);
        } else {
            echo json_encode(['error' => 'Invalid request']);
        }
        break;

    default:
        echo json_encode(['error' => 'Unsupported request method']);
        break;
}



// Routes handling for magazine
switch ($requestMethod) {
    // Existing cases for product management...

    // Add a product to the warehouse
    // Add a product to the warehouse
case 'POST':
    if (count($requestUriParts) === 4 && $requestUriParts[2] === 'magazzino') {
        $productId = (int)$requestUriParts[3]; // Get the product ID from the URL
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        // Validate input
        if (!isset($data['quantita'], $data['data_scadenza'])) {
            echo json_encode(['error' => 'Invalid input, quantity and expiration date are required']);
            exit;
        }

        // Check if the product exists
        $productExists = false;
        foreach ($products as $product) {
            if ($product['id'] === $productId) {
                $productExists = true;
                break;
            }
        }

        if (!$productExists) {
            echo json_encode(['error' => 'Product does not exist']);
            exit;
        }

        // Load existing warehouse data
        $warehouseFile = 'warehouse.json'; // Path to warehouse data file
        $warehouse = [];
        
        if (file_exists($warehouseFile)) {
            $warehouse = json_decode(file_get_contents($warehouseFile), true);
        }

        // Find the maximum existing batch ID for the same product
        $maxBatchId = 0;
        foreach ($warehouse as $item) {
            if ($item['prodottoID'] === $productId) {
                if ($item['id_batch'] > $maxBatchId) {
                    $maxBatchId = $item['id_batch'];
                }
            }
        }

        // Set the new batch ID
        $data['id_batch'] = $maxBatchId + 1; // Increment batch ID
        $data['prodottoID'] = $productId; // Associate with product ID
        
        // Add the product to the warehouse
        $warehouse[] = $data;
        saveWarehouseToFile($warehouse); // Save warehouse data to file
        echo json_encode($data); // Return the added product data with the new batch ID
    }
    break;

    



    case 'GET':
        // return all items
        if(count($requestUriParts) === 3&& $requestUriParts[2] === 'ricerca_magazzino') 
        {
            // Load the warehouse data (assuming it's stored in a file like warehouse.json)
            $warehouseFile = 'warehouse.json'; // Replace with your actual warehouse file path
            $warehouseItems = [];
        
            if (file_exists($warehouseFile)) {
                $warehouseItems = json_decode(file_get_contents($warehouseFile), true);
            }
        
            // Return the warehouse items as JSON
            echo json_encode($warehouseItems);
            exit;
        }


        elseif (count($requestUriParts) === 4 && $requestUriParts[2] === 'magazzino') {
            $productId = (int)$requestUriParts[3]; // Get the product ID from the URL
    
            // Load warehouse data
            $warehouseFile = 'warehouse.json'; // Path to warehouse data file
            $warehouseItems = [];
    
            if (file_exists($warehouseFile)) {
                $warehouseItems = json_decode(file_get_contents($warehouseFile), true);
            }
    
            // Search for the specific product
            $foundProduct = array_filter($warehouseItems, function($item) use ($productId) {
                return $item['prodottoID'] === $productId;
            });
    
            // Return the found product or an error message
            if (!empty($foundProduct)) {
                echo json_encode(array_values($foundProduct)); // Return the product(s) as an array
            } else {
                echo json_encode(['error' => 'Product not found']);
            }
            exit;
        }
        
        // Search for expired products by expiration date
        elseif (count($requestUriParts) === 4 && $requestUriParts[2] === 'ricerca_magazzino') {
            $warehouseFile = 'warehouse.json'; // Replace with your actual warehouse file path
            $warehouseItems = [];
        
            if (file_exists($warehouseFile)) {
                $warehouseItems = json_decode(file_get_contents($warehouseFile), true);
            }

            $expirationDate = $requestUriParts[3]; // Get the expiration date from the URL
            $foundProducts = array_filter($warehouseItems, function($product) use ($expirationDate) {
                return $product['data_scadenza'] < $expirationDate;
            });
            
            if (!empty($foundProducts)) {
                echo json_encode(array_values($foundProducts));
            } else {
                echo json_encode(['error' => 'No expired products found for the specified date']);
            }
        }
        break;
    

    // Delete a product from the warehouse
    case 'DELETE':
        if (count($requestUriParts) === 5 && $requestUriParts[2] === 'magazzino') {
            $productId = (int)$requestUriParts[3]; // Get the product ID from the URL
            $batchId = (int)$requestUriParts[4]; // Get the batch ID from the URL
    
            // Load warehouse data
            $warehouseFile = 'warehouse.json'; // Path to warehouse data file
            $warehouseItems = [];
    
            if (file_exists($warehouseFile)) {
                $warehouseItems = json_decode(file_get_contents($warehouseFile), true);
            }
    
            // Remove the batch
            foreach ($warehouseItems as $index => $item) {
                if ($item['prodottoID'] === $productId && $item['id_batch'] === $batchId) {
                    unset($warehouseItems[$index]); // Remove the item
                    // Save updated warehouse data
                    saveWarehouseToFile(array_values($warehouseItems));
                    echo json_encode(['message' => 'Batch deleted successfully']);
                    exit;
                }
            }
    
            echo json_encode(['error' => 'Product or batch not found']);
        } else {
            echo json_encode(['error' => 'Invalid request']);
        }
        break;
    
    
    

    default:
        echo json_encode(['error' => 'Unsupported request method']);
        break;
}

// Function to save products to a JSON file
function saveProductsToFile($products) {
    global $productFile;
    file_put_contents($productFile, json_encode($products, JSON_PRETTY_PRINT));
}

// Function to save warehouse data to a JSON file
function saveWarehouseToFile($warehouseData) {
    $warehouseFile = 'warehouse.json'; // Make sure this is defined
    file_put_contents($warehouseFile, json_encode($warehouseData, JSON_PRETTY_PRINT));
}


// Function to log requests to a JSON file (optional)
function logRequest($method, $uri) {
    $requestLogFile = 'request_log.json'; // Path to log file
    $logEntry = [
        'method' => $method,
        'uri' => $uri,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Read existing log entries
    $currentLogs = file_exists($requestLogFile) ? json_decode(file_get_contents($requestLogFile), true) : [];
    if (!is_array($currentLogs)) {
        $currentLogs = [];
    }

    // Append the new log entry
    $currentLogs[] = $logEntry;

    // Write the logs back to the file
    file_put_contents($requestLogFile, json_encode($currentLogs, JSON_PRETTY_PRINT));
}
?>

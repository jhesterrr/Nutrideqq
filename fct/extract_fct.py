import time
import json
import csv
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, NoSuchElementException

# Configuration
CHROME_DRIVER_PATH = "path/to/chromedriver" # Update this
BASE_URL = "https://i.fnri.dost.gov.ph/fct/library/search_item"

def setup_driver():
    chrome_options = Options()
    # chrome_options.add_argument("--headless") # Run headless for production
    driver = webdriver.Chrome(service=Service(CHROME_DRIVER_PATH), options=chrome_options)
    return driver

def extract_fct_data():
    driver = setup_driver()
    driver.get(BASE_URL)
    wait = WebDriverWait(driver, 20)
    
    results = []
    
    try:
        while True:
            # Wait for the table to load
            wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "table#food-items-table")))
            
            # Find all "View" buttons
            view_buttons = driver.find_elements(By.XPATH, "//button[contains(text(), 'View')]")
            
            for i in range(len(view_buttons)):
                # Re-fetch buttons to avoid stale element reference
                current_buttons = driver.find_elements(By.XPATH, "//button[contains(text(), 'View')]")
                btn = current_buttons[i]
                
                # Get Basic Info from the row
                row = btn.find_element(By.XPATH, "./ancestor::tr")
                cells = row.find_elements(By.TAG_NAME, "td")
                food_id = cells[0].text
                food_name = cells[1].text
                
                # Click View to open modal
                driver.execute_script("arguments[0].click();", btn)
                time.sleep(1) # Wait for modal animation
                
                # Wait for modal content
                wait.until(EC.visibility_of_element_located((By.ID, "food-detail-modal")))
                
                # Extract Detailed Nutrients
                nutrients = {}
                nutrient_rows = driver.find_elements(By.CSS_SELECTOR, ".modal-body table tr")
                for n_row in nutrient_rows:
                    cols = n_row.find_elements(By.TAG_NAME, "td")
                    if len(cols) >= 2:
                        name = cols[0].text.strip()
                        val_unit = cols[1].text.strip()
                        nutrients[name] = val_unit
                
                results.append({
                    "food_id": food_id,
                    "food_name": food_name,
                    "nutrients": nutrients
                })
                
                # Close Modal
                close_btn = driver.find_element(By.CSS_SELECTOR, "#food-detail-modal .close")
                close_btn.click()
                time.sleep(0.5)
            
            # Check for next page
            try:
                next_btn = driver.find_element(By.XPATH, "//li[contains(@class, 'next')]/a")
                if "disabled" in next_btn.get_attribute("parentElement").get_attribute("class"):
                    break
                next_btn.click()
                time.sleep(2) # Wait for page load
            except NoSuchElementException:
                break
                
    finally:
        driver.quit()
        
    return results

def save_to_csv(data, filename="fct_data.csv"):
    if not data:
        return
    
    # Flatten data for CSV
    headers = ["food_id", "food_name"]
    # Get all unique nutrient names
    nutrient_names = set()
    for item in data:
        nutrient_names.update(item["nutrients"].keys())
    headers.extend(sorted(list(nutrient_names)))
    
    with open(filename, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=headers)
        writer.writeheader()
        for item in data:
            row = {"food_id": item["food_id"], "food_name": item["food_name"]}
            row.update(item["nutrients"])
            writer.writerow(row)

if __name__ == "__main__":
    print("Starting extraction...")
    # data = extract_fct_data() # Uncomment to run
    # save_to_csv(data)
    print("Extraction logic ready. Set CHROME_DRIVER_PATH and run.")

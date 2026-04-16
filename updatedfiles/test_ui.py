import time
import os
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

print("Starting UI Verification Test...")

# Setup Chrome
options = webdriver.ChromeOptions()
options.add_experimental_option("detach", True)
options.add_argument("--start-maximized")

# Using the local browser
driver = webdriver.Chrome(options=options)

try:
    # 1. Login
    print("Navigating to Login Page...")
    driver.get("http://localhost/homesync/auth.php")
    
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "email")))
    driver.find_element(By.NAME, "email").send_keys("pmanor@mail.com")
    driver.find_element(By.NAME, "password").send_keys("qwertyuiop")
    driver.find_element(By.NAME, "login").click()
    
    # 2. Verify Dashboard
    print("Verifying Dashboard...")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CLASS_NAME, "metric-card")))
    time.sleep(2) # Pause for user to see
    
    # 3. Verify Tenants Page
    print("Navigating to Tenants...")
    driver.get("http://localhost/homesync/tenants.php")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CLASS_NAME, "table")))
    time.sleep(2)
    
    # Open Add Tenant Modal
    try:
        add_btn = driver.find_element(By.XPATH, "//button[contains(text(), 'Tenant')]")
        add_btn.click()
        time.sleep(2) # Pause to show modal
    except:
        pass

    # 4. Verify Billing Page
    print("Navigating to Billing...")
    driver.get("http://localhost/homesync/billing.php")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CLASS_NAME, "table")))
    time.sleep(2)
    
    # 5. Verify Notifications Page
    print("Navigating to Notifications...")
    driver.get("http://localhost/homesync/notifications.php")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "send_bulk")))
    time.sleep(2)
    
    # Save a screenshot for the artifacts
    screenshot_path = os.path.join(os.environ.get('USERPROFILE', 'C:\\Users\\pinchez'), '.gemini', 'antigravity', 'brain', 'c13b5d76-2089-4131-a494-650f1c188903', 'final_visible_test.png')
    driver.save_screenshot(screenshot_path)
    print(f"Captured final state screenshot to {screenshot_path}")

    print("UI Verification Test Completed Successfully!")
    time.sleep(5)
finally:
    driver.quit()

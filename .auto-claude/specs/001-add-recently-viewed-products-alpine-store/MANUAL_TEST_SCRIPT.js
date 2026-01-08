/**
 * Recently Viewed Products - Manual Test Script
 *
 * Usage:
 * 1. Open browser developer console
 * 2. Copy and paste this entire script
 * 3. Run the tests to verify functionality
 *
 * Each test function can be run independently:
 * - testBasicAdd()
 * - testDuplicateHandling()
 * - testMaxLimit()
 * - testClearFunctionality()
 * - testHasMethod()
 * - testPersistence()
 * - runAllTests()
 */

// Helper function to generate mock product data
function createMockProduct(id, title = null) {
  return {
    id: String(id),
    title: title || `Test Product ${id}`,
    url: `/product/${id}`,
    imageId: `img-${id}`,
    price: '€' + (10 + id) + '.00',
    promoPrice: id % 2 === 0 ? '€' + (8 + id) + '.00' : null,
    isPromo: id % 2 === 0
  };
}

// Helper function to log test results
function logTest(testName, passed, message = '') {
  const emoji = passed ? '✅' : '❌';
  const status = passed ? 'PASSED' : 'FAILED';
  console.log(`${emoji} ${testName}: ${status}${message ? ' - ' + message : ''}`);
  return passed;
}

// Test 1: Basic Add Functionality
function testBasicAdd() {
  console.log('\n=== Test 1: Basic Add Functionality ===');

  // Clear first
  window.Moderna.recentlyViewed.clear();

  // Add a product
  const product1 = createMockProduct(1);
  window.Moderna.recentlyViewed.add(product1);

  // Check if added
  const items = window.Moderna.recentlyViewed.items();
  const hasProduct = window.Moderna.recentlyViewed.has('1');
  const count = window.Moderna.recentlyViewed.count();

  const test1 = logTest('Product added', items.length === 1);
  const test2 = logTest('Has method works', hasProduct === true);
  const test3 = logTest('Count is correct', count === 1);
  const test4 = logTest('ViewedAt timestamp added', items[0].viewedAt !== undefined);

  return test1 && test2 && test3 && test4;
}

// Test 2: Duplicate Handling
function testDuplicateHandling() {
  console.log('\n=== Test 2: Duplicate Handling ===');

  // Clear and add products
  window.Moderna.recentlyViewed.clear();
  window.Moderna.recentlyViewed.add(createMockProduct(1, 'Product A'));
  window.Moderna.recentlyViewed.add(createMockProduct(2, 'Product B'));
  window.Moderna.recentlyViewed.add(createMockProduct(3, 'Product C'));

  const items1 = window.Moderna.recentlyViewed.items();
  const firstViewedAt = items1.find(item => item.id === '1').viewedAt;

  // Wait a bit and view product 1 again
  setTimeout(() => {
    window.Moderna.recentlyViewed.add(createMockProduct(1, 'Product A'));

    const items2 = window.Moderna.recentlyViewed.items();
    const secondViewedAt = items2.find(item => item.id === '1').viewedAt;

    const test1 = logTest('No duplicate entries', items2.length === 3, `Count: ${items2.length}`);
    const test2 = logTest('Product moved to front', items2[0].id === '1');
    const test3 = logTest('ViewedAt updated', secondViewedAt > firstViewedAt);

    return test1 && test2 && test3;
  }, 100);
}

// Test 3: Max Limit Enforcement
function testMaxLimit() {
  console.log('\n=== Test 3: Max Limit Enforcement (12 items) ===');

  // Clear and add 15 products
  window.Moderna.recentlyViewed.clear();

  for (let i = 1; i <= 15; i++) {
    window.Moderna.recentlyViewed.add(createMockProduct(i));
  }

  const items = window.Moderna.recentlyViewed.items();
  const count = window.Moderna.recentlyViewed.count();

  // Check that only 12 items remain
  const test1 = logTest('Max 12 items enforced', count === 12, `Count: ${count}`);

  // Check that oldest items were removed (1, 2, 3 should be gone)
  const has1 = window.Moderna.recentlyViewed.has('1');
  const has2 = window.Moderna.recentlyViewed.has('2');
  const has3 = window.Moderna.recentlyViewed.has('3');
  const test2 = logTest('Oldest items removed', !has1 && !has2 && !has3);

  // Check that newest items remain (4-15)
  const has15 = window.Moderna.recentlyViewed.has('15');
  const has14 = window.Moderna.recentlyViewed.has('14');
  const test3 = logTest('Newest items retained', has15 && has14);

  return test1 && test2 && test3;
}

// Test 4: Clear Functionality
function testClearFunctionality() {
  console.log('\n=== Test 4: Clear Functionality ===');

  // Add some products
  window.Moderna.recentlyViewed.clear();
  window.Moderna.recentlyViewed.add(createMockProduct(1));
  window.Moderna.recentlyViewed.add(createMockProduct(2));
  window.Moderna.recentlyViewed.add(createMockProduct(3));

  const beforeCount = window.Moderna.recentlyViewed.count();
  const test1 = logTest('Products added', beforeCount === 3, `Count: ${beforeCount}`);

  // Clear
  window.Moderna.recentlyViewed.clear();

  const afterCount = window.Moderna.recentlyViewed.count();
  const test2 = logTest('Clear removes all', afterCount === 0, `Count: ${afterCount}`);

  // Check localStorage is also cleared
  const stored = localStorage.getItem('moderna_recently_viewed');
  const parsed = JSON.parse(stored || '[]');
  const test3 = logTest('LocalStorage cleared', parsed.length === 0);

  return test1 && test2 && test3;
}

// Test 5: Has Method
function testHasMethod() {
  console.log('\n=== Test 5: Has Method ===');

  window.Moderna.recentlyViewed.clear();
  window.Moderna.recentlyViewed.add(createMockProduct(100));

  const test1 = logTest('Has method - exists', window.Moderna.recentlyViewed.has('100'));
  const test2 = logTest('Has method - not exists', !window.Moderna.recentlyViewed.has('999'));

  // Test with number vs string
  const test3 = logTest('Has method - number ID', window.Moderna.recentlyViewed.has(100));

  return test1 && test2 && test3;
}

// Test 6: Persistence (localStorage)
function testPersistence() {
  console.log('\n=== Test 6: Persistence (localStorage) ===');

  window.Moderna.recentlyViewed.clear();
  window.Moderna.recentlyViewed.add(createMockProduct(1));
  window.Moderna.recentlyViewed.add(createMockProduct(2));

  // Check localStorage
  const stored = localStorage.getItem('moderna_recently_viewed');
  const test1 = logTest('localStorage exists', stored !== null);

  const parsed = JSON.parse(stored || '[]');
  const test2 = logTest('localStorage has correct count', parsed.length === 2);
  const test3 = logTest('localStorage has correct structure',
    parsed[0].id !== undefined &&
    parsed[0].title !== undefined &&
    parsed[0].viewedAt !== undefined
  );

  console.log('localStorage content:', parsed);

  return test1 && test2 && test3;
}

// Test 7: Remove Method
function testRemoveMethod() {
  console.log('\n=== Test 7: Remove Method ===');

  window.Moderna.recentlyViewed.clear();
  window.Moderna.recentlyViewed.add(createMockProduct(1));
  window.Moderna.recentlyViewed.add(createMockProduct(2));
  window.Moderna.recentlyViewed.add(createMockProduct(3));

  const beforeCount = window.Moderna.recentlyViewed.count();
  const test1 = logTest('Initial count correct', beforeCount === 3);

  // Remove product 2
  window.Moderna.recentlyViewed.remove('2');

  const afterCount = window.Moderna.recentlyViewed.count();
  const test2 = logTest('Remove decreases count', afterCount === 2);

  const hasRemoved = window.Moderna.recentlyViewed.has('2');
  const test3 = logTest('Removed product not found', !hasRemoved);

  const hasOthers = window.Moderna.recentlyViewed.has('1') && window.Moderna.recentlyViewed.has('3');
  const test4 = logTest('Other products still exist', hasOthers);

  return test1 && test2 && test3 && test4;
}

// Test 8: Event Dispatching
function testEventDispatching() {
  console.log('\n=== Test 8: Event Dispatching ===');

  let addedEventFired = false;
  let removedEventFired = false;
  let clearedEventFired = false;

  // Setup event listeners
  window.addEventListener('recentlyviewed:added', () => { addedEventFired = true; }, { once: true });
  window.addEventListener('recentlyviewed:removed', () => { removedEventFired = true; }, { once: true });
  window.addEventListener('recentlyviewed:cleared', () => { clearedEventFired = true; }, { once: true });

  window.Moderna.recentlyViewed.clear();

  setTimeout(() => {
    window.Moderna.recentlyViewed.add(createMockProduct(1));

    setTimeout(() => {
      const test1 = logTest('Added event fired', addedEventFired);

      window.Moderna.recentlyViewed.remove('1');

      setTimeout(() => {
        const test2 = logTest('Removed event fired', removedEventFired);

        window.Moderna.recentlyViewed.clear();

        setTimeout(() => {
          const test3 = logTest('Cleared event fired', clearedEventFired);
          return test1 && test2 && test3;
        }, 50);
      }, 50);
    }, 50);
  }, 50);
}

// Test 9: Data Structure
function testDataStructure() {
  console.log('\n=== Test 9: Data Structure ===');

  window.Moderna.recentlyViewed.clear();

  const product = createMockProduct(1, 'Test Product');
  window.Moderna.recentlyViewed.add(product);

  const items = window.Moderna.recentlyViewed.items();
  const item = items[0];

  const test1 = logTest('Has id', item.id !== undefined);
  const test2 = logTest('Has title', item.title !== undefined);
  const test3 = logTest('Has url', item.url !== undefined);
  const test4 = logTest('Has imageId', item.imageId !== undefined);
  const test5 = logTest('Has price', item.price !== undefined);
  const test6 = logTest('Has isPromo', item.isPromo !== undefined);
  const test7 = logTest('Has viewedAt', item.viewedAt !== undefined);
  const test8 = logTest('viewedAt is timestamp', typeof item.viewedAt === 'number');

  console.log('Sample item:', item);

  return test1 && test2 && test3 && test4 && test5 && test6 && test7 && test8;
}

// Run all tests
function runAllTests() {
  console.clear();
  console.log('🧪 Recently Viewed Products - Test Suite');
  console.log('=========================================\n');

  const results = [];

  results.push(testBasicAdd());
  results.push(testHasMethod());
  results.push(testRemoveMethod());
  results.push(testMaxLimit());
  results.push(testClearFunctionality());
  results.push(testPersistence());
  results.push(testDataStructure());

  // Tests with delays
  setTimeout(() => {
    results.push(testDuplicateHandling());

    setTimeout(() => {
      testEventDispatching();

      setTimeout(() => {
        const passed = results.filter(r => r).length;
        const total = results.length;

        console.log('\n=========================================');
        console.log(`📊 Test Results: ${passed}/${total} passed`);

        if (passed === total) {
          console.log('✅ All tests passed!');
        } else {
          console.log(`❌ ${total - passed} test(s) failed`);
        }

        console.log('\n📝 Manual Testing Checklist:');
        console.log('  1. Navigate to a product page');
        console.log('  2. Verify the product appears in recently viewed section');
        console.log('  3. Navigate to another product');
        console.log('  4. Verify both products appear, newest first');
        console.log('  5. Refresh the page - products should persist');
        console.log('  6. On product page, verify current product is excluded');
        console.log('  7. Test responsive design at different screen sizes');
        console.log('  8. Check browser console for any errors');
      }, 500);
    }, 500);
  }, 500);
}

// Instructions
console.log('🧪 Recently Viewed Products - Test Script Loaded');
console.log('');
console.log('Run tests:');
console.log('  runAllTests()     - Run all automated tests');
console.log('  testBasicAdd()    - Test basic add functionality');
console.log('  testDuplicateHandling() - Test duplicate product handling');
console.log('  testMaxLimit()    - Test 12-item limit');
console.log('  testClearFunctionality() - Test clear method');
console.log('');
console.log('Quick checks:');
console.log('  window.Moderna.recentlyViewed.items()  - View all items');
console.log('  window.Moderna.recentlyViewed.count()  - Get count');
console.log('  window.Moderna.recentlyViewed.clear()  - Clear all');
console.log('');
console.log('Run: runAllTests() to start testing');

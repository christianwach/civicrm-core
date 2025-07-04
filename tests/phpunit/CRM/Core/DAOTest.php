<?php

use Civi\Api4\CustomField;
use Civi\Api4\CustomGroup;

/**
 * Class CRM_Core_DAOTest
 * @group headless
 */
class CRM_Core_DAOTest extends CiviUnitTestCase {

  const ABORTED_SQL = "_aborted_sql_";

  protected function tearDown(): void {
    $this->quickCleanup([], TRUE);
    parent::tearDown();
  }

  public function testGetReferenceColumns(): void {
    // choose CRM_Core_DAO_Email as an arbitrary example
    $emailRefs = CRM_Core_DAO_Email::getReferenceColumns();
    $refsByTarget = [];
    foreach ($emailRefs as $refSpec) {
      $refsByTarget[$refSpec->getTargetTable()] = $refSpec;
    }
    $this->assertTrue(array_key_exists('civicrm_contact', $refsByTarget));
    $contactRef = $refsByTarget['civicrm_contact'];
    $this->assertEquals('contact_id', $contactRef->getReferenceKey());
    $this->assertEquals('id', $contactRef->getTargetKey());
    $this->assertInstanceOf(\CRM_Core_Reference_Basic::class, $contactRef);
  }

  public function testGetDynamicReferenceColumns(): void {
    // CRM_Core_DAO_EntityTag has an example of all 3 types of references
    $referenceColumns = [];
    foreach (CRM_Core_DAO_EntityTag::getReferenceColumns() as $reference) {
      $this->assertEquals('civicrm_entity_tag', $reference->getReferenceTable());
      $referenceColumns[$reference->getReferenceKey()] = $reference;
    }

    $reference = $referenceColumns['entity_table'];
    $this->assertInstanceOf(\CRM_Core_Reference_OptionValue::class, $reference);
    $this->assertNull($reference->getTypeColumn());
    $this->assertEquals('civicrm_option_value', $reference->getTargetTable());
    $this->assertEquals('value', $reference->getTargetKey());

    $reference = $referenceColumns['tag_id'];
    $this->assertInstanceOf(\CRM_Core_Reference_Basic::class, $reference);
    $this->assertNull($reference->getTypeColumn());
    $this->assertEquals('civicrm_tag', $reference->getTargetTable());
    $this->assertEquals('id', $reference->getTargetKey());

    $reference = $referenceColumns['entity_id'];
    $this->assertInstanceOf(\CRM_Core_Reference_Dynamic::class, $reference);
    $this->assertEquals('entity_table', $reference->getTypeColumn());
    $this->assertNull($reference->getTargetTable());
    $this->assertEquals('id', $reference->getTargetKey());
  }

  public function testGetReferencesToTable(): void {
    $refs = CRM_Core_DAO::getReferencesToTable(CRM_Financial_DAO_FinancialType::getTableName());
    $refsBySource = [];
    foreach ($refs as $refSpec) {
      $refsBySource[$refSpec->getReferenceTable()] = $refSpec;
    }
    $this->assertTrue(array_key_exists('civicrm_entity_financial_account', $refsBySource));
    $genericRef = $refsBySource['civicrm_entity_financial_account'];
    $this->assertEquals('entity_id', $genericRef->getReferenceKey());
    $this->assertEquals('entity_table', $genericRef->getTypeColumn());
    $this->assertEquals('id', $genericRef->getTargetKey());
    $this->assertEquals('CRM_Core_Reference_Dynamic', get_class($genericRef));
  }

  public function testFindReferences(): void {
    $params = [
      'first_name' => 'Testy',
      'last_name' => 'McScallion',
      'contact_type' => 'Individual',
    ];

    $contactID = $this->callAPISuccess('Contact', 'create', $params)['id'];

    $params = [
      'email' => 'spam@dev.null',
      'contact_id' => $contactID,
      'is_primary' => 0,
      'location_type_id' => 1,
    ];

    $this->callAPISuccess('Email', 'create', $params);

    $contact = new CRM_Contact_BAO_Contact();
    $contact->id = $contactID;
    $refs = $contact->findReferences();
    $refsByTable = [];
    foreach ($refs as $refObj) {
      $refsByTable[$refObj->__table] = $refObj;
    }

    $this->assertTrue(array_key_exists('civicrm_email', $refsByTable));
    $refDao = $refsByTable['civicrm_email'];
    $refDao->find(TRUE);
    $this->assertEquals($contactID, $refDao->contact_id);
  }

  /**
   * @return array
   */
  public static function composeQueryExamples() {
    $cases = [];
    // $cases[] = array('Input-SQL', 'Input-Params', 'Expected-SQL');

    $cases[0] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['', 'String']], 'UPDATE civicrm_foo SET bar = \'\''];
    $cases[1] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['the text', 'String']], 'UPDATE civicrm_foo SET bar = \'the text\''];
    $cases[2] = ['UPDATE civicrm_foo SET bar = %1', [1 => [NULL, 'String']], self::ABORTED_SQL];
    $cases[3] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['null', 'String']], 'UPDATE civicrm_foo SET bar = NULL'];

    $cases[3] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['', 'Float']], self::ABORTED_SQL];
    $cases[4] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['1.23', 'Float']], 'UPDATE civicrm_foo SET bar = 1.23'];
    $cases[5] = ['UPDATE civicrm_foo SET bar = %1', [1 => [NULL, 'Float']], self::ABORTED_SQL];
    $cases[6] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['null', 'Float']], self::ABORTED_SQL];

    $cases[11] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['', 'Money']], self::ABORTED_SQL];
    $cases[12] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['1.23', 'Money']], 'UPDATE civicrm_foo SET bar = 1.23'];
    $cases[13] = ['UPDATE civicrm_foo SET bar = %1', [1 => [NULL, 'Money']], self::ABORTED_SQL];
    $cases[14] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['null', 'Money']], self::ABORTED_SQL];

    $cases[15] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['', 'Int']], self::ABORTED_SQL];
    $cases[16] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['123', 'Int']], 'UPDATE civicrm_foo SET bar = 123'];
    $cases[17] = ['UPDATE civicrm_foo SET bar = %1', [1 => [NULL, 'Int']], self::ABORTED_SQL];
    $cases[18] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['null', 'Int']], self::ABORTED_SQL];

    $cases[19] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['', 'Timestamp']], 'UPDATE civicrm_foo SET bar = null'];
    $cases[20] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['20150102030405', 'Timestamp']], 'UPDATE civicrm_foo SET bar = 20150102030405'];
    $cases[21] = ['UPDATE civicrm_foo SET bar = %1', [1 => [NULL, 'Timestamp']], 'UPDATE civicrm_foo SET bar = null'];
    $cases[22] = ['UPDATE civicrm_foo SET bar = %1', [1 => ['null', 'Timestamp']], self::ABORTED_SQL];

    // CASE: No params
    $cases[1000] = [
      'SELECT * FROM whatever',
      [],
      'SELECT * FROM whatever',
    ];

    // CASE: Integer param
    $cases[1001] = [
      'SELECT * FROM whatever WHERE id = %1',
      [
        1 => [10, 'Integer'],
      ],
      'SELECT * FROM whatever WHERE id = 10',
    ];

    // CASE: String param
    $cases[1002] = [
      'SELECT * FROM whatever WHERE name = %1',
      [
        1 => ['Alice', 'String'],
      ],
      'SELECT * FROM whatever WHERE name = \'Alice\'',
    ];

    // CASE: Two params
    $cases[1003] = [
      'SELECT * FROM whatever WHERE name = %1 AND title = %2',
      [
        1 => ['Alice', 'String'],
        2 => ['Bob', 'String'],
      ],
      'SELECT * FROM whatever WHERE name = \'Alice\' AND title = \'Bob\'',
    ];

    // CASE: Two params with special character (%1)
    $cases[1004] = [
      'SELECT * FROM whatever WHERE name = %1 AND title = %2',
      [
        1 => ['Alice %2', 'String'],
        2 => ['Bob', 'String'],
      ],
      'SELECT * FROM whatever WHERE name = \'Alice %2\' AND title = \'Bob\'',
    ];

    // CASE: Two params with special character ($1)
    $cases[1005] = [
      'SELECT * FROM whatever WHERE name = %1 AND title = %2',
      [
        1 => ['Alice $1', 'String'],
        2 => ['Bob', 'String'],
      ],
      'SELECT * FROM whatever WHERE name = \'Alice $1\' AND title = \'Bob\'',
    ];

    return $cases;
  }

  /**
   * @dataProvider composeQueryExamples
   * @param $inputSql
   * @param $inputParams
   * @param $expectSql
   */
  public function testComposeQuery($inputSql, $inputParams, $expectSql) {
    try {
      $actualSql = CRM_Core_DAO::composeQuery($inputSql, $inputParams);
    }
    catch (Exception $e) {
      $actualSql = self::ABORTED_SQL;
    }
    $this->assertEquals($expectSql, $actualSql);
  }

  /**
   * CASE: Two params where the %2 is already present in the query
   * NOTE: This case should rightly FAIL, as using strstr in the replace mechanism will turn
   * the query into: SELECT * FROM whatever WHERE name = 'Alice' AND title = 'Bob' AND year LIKE ''Bob'012'
   * So, to avoid such ERROR, the query should be framed like:
   * 'SELECT * FROM whatever WHERE name = %1 AND title = %3 AND year LIKE '%2012'
   * $params[3] = array('Bob', 'String');
   * i.e. the place holder should be unique and should not contain in any other operational use in query
   */
  public function testComposeQueryFailure(): void {
    $cases[] = [
      'SELECT * FROM whatever WHERE name = %1 AND title = %2 AND year LIKE \'%2012\' ',
      [
        1 => ['Alice', 'String'],
        2 => ['Bob', 'String'],
      ],
      'SELECT * FROM whatever WHERE name = \'Alice\' AND title = \'Bob\' AND year LIKE \'%2012\' ',
    ];
    [$inputSql, $inputParams, $expectSql] = $cases[0];
    $actualSql = CRM_Core_DAO::composeQuery($inputSql, $inputParams);
    $this->assertFalse(($expectSql == $actualSql));
    unset($scope);
  }

  /**
   * @return array
   */
  public static function sqlNameDataProvider() {
    return [
      ['this is a long string', 30, FALSE, 'this is a long string'],
      [
        'this is an even longer string which is exactly 60 character',
        60,
        FALSE,
        'this is an even longer string which is exactly 60 character',
      ],
      [
        'this is an even longer string which is exactly 60 character',
        60,
        TRUE,
        'this is an even longer string which is exactly 60 character',
      ],
      [
        'this is an even longer string which is a bit more than 60 character',
        60,
        FALSE,
        'this is an even longer string which is a bit more than 60 ch',
      ],
      [
        'this is an even longer string which is a bit more than 60 character',
        60,
        TRUE,
        'this is an even longer string which is a bit more th_c1cbd519',
      ],
    ];
  }

  /**
   * @dataProvider sqlNameDataProvider
   * @param $inputData
   * @param $length
   * @param $makeRandom
   * @param $expectedResult
   */
  public function testShortenSQLName($inputData, $length, $makeRandom, $expectedResult) {
    $this->assertEquals($expectedResult, CRM_Core_DAO::shortenSQLName($inputData, $length, $makeRandom));
  }

  public function testFindById(): void {
    $existing_contact = $this->individualCreate();
    /** @var CRM_Contact_DAO_Contact $contact */
    $contact = CRM_Contact_BAO_Contact::findById($existing_contact);
    $this->assertEquals($existing_contact, $contact->id);
    $deleted_contact_id = $existing_contact;
    $this->contactDelete($contact->id);
    $exception_thrown = FALSE;
    try {
      CRM_Contact_BAO_Contact::findById($deleted_contact_id);
    }
    catch (Exception $e) {
      $exception_thrown = TRUE;
    }
    $this->assertTrue($exception_thrown);
  }

  /**
   * requireSafeDBName() method (to check valid database name)
   */
  public function testRequireSafeDBName(): void {
    $databases = [
      'testdb' => TRUE,
      'test_db' => TRUE,
      'TEST_db' => TRUE,
      '123testdb' => TRUE,
      'test12db34' => TRUE,
      'test_12_db34' => TRUE,
      'test-db' => TRUE,
      'test;db' => FALSE,
      'test*&db' => FALSE,
      'testdb;Delete test' => FALSE,
      '123456' => FALSE,
      'test#$%^&*' => FALSE,
    ];
    $testDetails = [];
    foreach ($databases as $database => $val) {
      $this->assertEquals(CRM_Core_DAO::requireSafeDBName($database), $val);
    }
  }

  /**
   * Test the function designed to find myIsam tables.
   */
  public function testMyISAMCheck(): void {
    // Cleanup previous, failed tests.
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS civicrm_my_isam');

    // A manually created MyISAM table should raise a redflag.
    $this->assertEquals(0, CRM_Core_DAO::isDBMyISAM());
    CRM_Core_DAO::executeQuery('CREATE TABLE civicrm_my_isam (`id` int(10) unsigned NOT NULL) ENGINE = MyISAM');
    $this->assertEquals(1, CRM_Core_DAO::isDBMyISAM());
    CRM_Core_DAO::executeQuery('DROP TABLE civicrm_my_isam');

    // A temp table should not raise flag.
    $tempTableName = CRM_Utils_SQL_TempTable::build()->setCategory('myisam')->getName();
    // A temp table should not raise flag (randomized naming).
    $this->assertEquals(0, CRM_Core_DAO::isDBMyISAM());
    CRM_Core_DAO::executeQuery("CREATE TABLE $tempTableName (`id` int(10) unsigned NOT NULL) ENGINE = MyISAM");
    // Ignore temp tables
    $this->assertEquals(0, CRM_Core_DAO::isDBMyISAM());
    CRM_Core_DAO::executeQuery("DROP TABLE $tempTableName");
  }

  /**
   * CRM-19930: Test toArray() function with $format param
   */
  public function testDAOtoArray(): void {
    $format = 'user[%s]';
    $params = [
      'first_name' => 'Testy',
      'last_name' => 'McScallion',
      'contact_type' => 'Individual',
    ];

    $contact = $this->callAPISuccess('Contact', 'create', $params);
    $query = "SELECT contact_type, display_name FROM civicrm_contact WHERE id={$contact['id']}";
    $toArray = [
      'contact_type' => 'Individual',
      'display_name' => 'Testy McScallion',
    ];
    $modifiedKeyArray = [];
    foreach ($toArray as $k => $v) {
      $modifiedKeyArray[sprintf($format, $k)] = $v;
    }

    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $daoToArray = $dao->toArray();
      $this->checkArrayEquals($toArray, $daoToArray);
      $daoToArray = $dao->toArray($format);
      $this->checkArrayEquals($modifiedKeyArray, $daoToArray);
    }
  }

  /**
   * CRM-17748: Test internal DAO options
   */
  public function testDBOptions(): void {
    $contactIDs = [];
    for ($i = 0; $i < 10; $i++) {
      $contactIDs[] = $this->individualCreate([
        'first_name' => 'Alan' . bin2hex(random_bytes(4)),
        'last_name' => 'Smith' . bin2hex(random_bytes(2)),
      ]);
    }

    // Test option 'result_buffering'
    $this->_testMemoryUsageForUnbufferedQuery();

    // cleanup
    foreach ($contactIDs as $contactID) {
      $this->callAPISuccess('Contact', 'delete', ['id' => $contactID]);
    }
  }

  /**
   * Helper function to test result of buffered and unbuffered query
   */
  public function _testMemoryUsageForUnbufferedQuery() {
    $sql = "SELECT * FROM civicrm_contact WHERE first_name LIKE 'Alan%' AND last_name LIKE 'Smith%' ";

    $dao = CRM_Core_DAO::executeQuery($sql);
    $contactsFetchedFromBufferedQuery = $dao->fetchAll();

    $dao = CRM_Core_DAO::executeUnbufferedQuery($sql);
    $contactsFetchedFromUnbufferedQuery = $dao->fetchAll();

    $this->checkArrayEquals($contactsFetchedFromBufferedQuery, $contactsFetchedFromUnbufferedQuery);
  }

  /**
   * Test that known sql modes are present in session.
   */
  public function testSqlModePresent(): void {
    $sqlModes = CRM_Utils_SQL::getSqlModes();
    // assert we have strict trans
    $this->assertContains('STRICT_TRANS_TABLES', $sqlModes);
    if (CRM_Utils_SQL::supportsFullGroupBy()) {
      $this->assertContains('ONLY_FULL_GROUP_BY', $sqlModes);
    }
  }

  /**
   * @return array
   * @throws \ReflectionException
   */
  public static function serializationMethods() {
    $constants = [];
    $simpleData = [
      NULL,
      ['Foo', 'Bar', '3', '4', '5'],
      [],
      ['0'],
    ];
    $complexData = [
      [
        'foo' => 'bar',
        'baz' => ['1', '2', '3', ['one', 'two']],
        '3' => '0',
      ],
    ];
    $daoInfo = new ReflectionClass('CRM_Core_DAO');
    foreach ($daoInfo->getConstants() as $constant => $val) {
      if ($constant === 'SERIALIZE_NONE') {
        continue;
      }
      if ($constant === 'SERIALIZE_JSON' || $constant === 'SERIALIZE_PHP') {
        $constants[] = [$val, array_merge($simpleData, $complexData)];
      }
      elseif (str_starts_with($constant, 'SERIALIZE_')) {
        $constants[] = [$val, $simpleData];
      }
    }
    return $constants;
  }

  public function testFetchGeneratorDao(): void {
    $this->individualCreate([], 0);
    $this->individualCreate([], 1);
    $this->individualCreate([], 2);
    $count = 0;
    $g = CRM_Core_DAO::executeQuery('SELECT contact_type FROM civicrm_contact WHERE contact_type = "Individual" LIMIT 3')
      ->fetchGenerator();
    foreach ($g as $row) {
      $this->assertEquals('Individual', $row->contact_type);
      $count++;
    }
    $this->assertEquals(3, $count);
  }

  public function testFetchGeneratorArray(): void {
    $this->individualCreate([], 0);
    $this->individualCreate([], 1);
    $this->individualCreate([], 2);
    $count = 0;
    $g = CRM_Core_DAO::executeQuery('SELECT contact_type FROM civicrm_contact WHERE contact_type = "Individual" LIMIT 3')
      ->fetchGenerator('array');
    foreach ($g as $row) {
      $this->assertEquals('Individual', $row['contact_type']);
      $count++;
    }
    $this->assertEquals(3, $count);
  }

  /**
   * @dataProvider serializationMethods
   */
  public function testFieldSerialization($method, $sampleData) {
    foreach ($sampleData as $value) {
      $serialized = CRM_Core_DAO::serializeField($value, $method);
      $newValue = CRM_Core_DAO::unSerializeField($serialized, $method);
      $this->assertEquals($value, $newValue);
    }
  }

  /**
   * Test the DAO cloning method does not hit issues with freeing the result.
   */
  public function testCloneDAO(): void {
    $dao = CRM_Core_DAO::executeQuery('SELECT * FROM civicrm_domain');
    $i = 0;
    while ($dao->fetch()) {
      $i++;
      $cloned = clone($dao);
      unset($cloned);
    }
    $this->assertEquals(2, $i);
  }

  /**
   * Test modifying a query in a hook.
   *
   * Test that adding a sensible string does not cause failure.
   *
   * @throws \Exception
   */
  public function testModifyQuery(): void {
    /**
     * @param \Civi\Core\Event\QueryEvent $e
     */
    $listener = function($e) {
      $e->query = '/* User :  hooked */' . $e->query;
    };
    Civi::dispatcher()->addListener('civi.db.query', $listener);
    CRM_Core_DAO::executeQuery('SELECT * FROM civicrm_domain');

    Civi::dispatcher()->removeListener('civi.db.query', $listener);
  }

  /**
   * Test modifying a query in a hook.
   *
   * Demonstrate it is modified showing the query now breaks.
   */
  public function testModifyAndBreakQuery(): void {
    $dbNameString = stripos(CRM_Utils_SQL::getDatabaseVersion(), 'mariadb') !== FALSE ? 'MariaDB' : 'MySQL';
    /**
     * @param \Civi\Core\Event\QueryEvent $e
     */
    $listener = function($e) {
      $e->query = '/* Forgot trailing comment marker' . $e->query;
    };
    Civi::dispatcher()->addListener('civi.db.query', $listener);
    try {
      CRM_Core_DAO::executeQuery('SELECT * FROM civicrm_domain');
    }
    catch (PEAR_Exception $e) {
      $this->assertEquals(
        "SELECT * FROM civicrm_domain [nativecode=1064 ** You have an error in your SQL syntax; check the manual that corresponds to your $dbNameString server version for the right syntax to use near '/* Forgot trailing comment markerSELECT * FROM civicrm_domain' at line 1]",
        $e->getCause()->getUserInfo()
      );
      Civi::dispatcher()->removeListener('civi.db.query', $listener);
      return;
    }
    Civi::dispatcher()->removeListener('civi.db.query', $listener);
    $this->fail('String not altered');
  }

  public function testSupportedFields(): void {
    // Hack a different db version which will trigger getSupportedFields to filter out newer fields
    CRM_Core_BAO_Domain::getDomain()->version = '5.26.0';

    $customGroupFields = CRM_Core_DAO_CustomGroup::getSupportedFields();
    // 'icon' was added in 5.28
    $this->assertArrayNotHasKey('icon', $customGroupFields);

    // Remove domain version override:
    CRM_Core_BAO_Domain::version(TRUE);

    $activityFields = CRM_Activity_DAO_Activity::getSupportedFields();
    // Fields should be indexed by name not unique_name (which is "activity_id")
    $this->assertEquals('id', $activityFields['id']['name']);

    $customGroupFields = CRM_Core_DAO_CustomGroup::getSupportedFields();
    $this->assertArrayHasKey('icon', $customGroupFields);

    \CRM_Core_Config::singleton()->userPermissionClass->permissions = ['access CiviCRM', 'view all contacts'];
    $contactFields = CRM_Contact_DAO_Contact::getSupportedFields();
    $this->assertArrayHasKey('api_key', $contactFields);

    $permissionedContactFields = CRM_Contact_DAO_Contact::getSupportedFields(TRUE);
    $this->assertArrayNotHasKey('api_key', $permissionedContactFields);
  }

  public function testTableHasBeenAdded(): void {
    // Hack a different db version
    CRM_Core_BAO_Domain::getDomain()->version = '5.28.0';

    // Table was added in 5.29
    $this->assertFalse(CRM_Contact_DAO_RelationshipCache::tableHasBeenAdded());

    // Remove domain version override:
    CRM_Core_BAO_Domain::version(TRUE);

    $this->assertTrue(CRM_Contact_DAO_RelationshipCache::tableHasBeenAdded());
  }

  /**
   * Test that a timestamp field that allows null doesn't crash when updating
   * with a blank date.
   * An unpatched PEAR::DB_DataObject will fail when sql_mode has NO_ZERO_DATE.
   *
   * @throws CRM_Core_Exception
   */
  public function testUpdateTimestampWithBlankDate(): void {
    // Arbitrarily using "Cache" since it has a desired type of timestamp field and is simple.
    $dao = new CRM_Core_DAO_Cache();
    $fields = $dao->fields();
    $this->assertSame(CRM_Utils_Type::T_TIMESTAMP, $fields['expired_date']['type'], 'Oh somebody changed the type, so this test might not be testing the right type of timestamp anymore. Might need to change the test to have it use a different field.');
    $this->assertFalse(!empty($fields['expired_date']['required']), 'Oh somebody changed the REQUIRED setting, so this test might not be testing the right type of timestamp anymore. Might need to change the test to have it use a different field.');

    $dao->group_name = 'mytest';
    $dao->path = 'mypath';
    $dao->data = 'some data';
    $dao->expired_date = '';
    $dao->save();
    $id = $dao->id;

    // Now retrieve it and update it with a blank timestamp.
    $dao = new CRM_Core_DAO_Cache();
    $dao->id = $id;
    $dao->find(TRUE);
    // sanity check we got the right one since otherwise checking null might falsely be true
    $this->assertEquals('mytest', $dao->group_name);
    $this->assertNull($dao->expired_date);

    $dao->data = 'some updated data';
    $dao->expired_date = '';
    // would crash here on update
    $dao->save();

    $dao = new CRM_Core_DAO_Cache();
    $dao->id = $id;
    $dao->find(TRUE);
    $this->assertEquals('some updated data', $dao->data);
    $this->assertNull($dao->expired_date);
  }

  public function testFillValues(): void {
    $label = uniqid();
    $saved = CRM_Contact_BAO_SavedSearch::writeRecord([
      'label' => $label,
    ]);
    $onlyId = ['id' => $saved->id];
    $onlyName = ['name' => $saved->name];
    $this->assertEquals($label, CRM_Contact_BAO_SavedSearch::fillValues($onlyId, ['label'])['label']);
    $this->assertEquals($label, CRM_Contact_BAO_SavedSearch::fillValues($onlyName, ['label'])['label']);
  }

  public function testGetReferencesToContactTable(): void {
    $group1 = CustomGroup::create(FALSE)
      ->setValues([
        'name' => 'cg1',
        'title' => 'Custom Group1',
        'extends' => 'Group',
      ])
      ->execute()->single();
    $group2 = CustomGroup::create(FALSE)
      ->setValues([
        'name' => 'cg2',
        'title' => 'Custom Group2',
        'extends' => 'Contact',
      ])
      ->execute()->single();

    $sampleFieldData = [
      ['custom_group_id' => $group1['id'], 'data_type' => 'ContactReference', 'label' => 'f1'],
      ['custom_group_id' => $group1['id'], 'fk_entity' => 'Household', 'label' => 'f2'],
      ['custom_group_id' => $group2['id'], 'fk_entity' => 'Individual', 'label' => 'f3'],
      ['custom_group_id' => $group2['id'], 'fk_entity' => 'Contact', 'label' => 'f4'],
      ['custom_group_id' => $group2['id'], 'fk_entity' => 'Activity', 'label' => 'f5'],
    ];
    $customField = CustomField::save(FALSE)
      ->setRecords($sampleFieldData)
      ->setDefaults(['html_type' => 'Text', 'data_type' => 'EntityReference'])
      ->execute();

    $expectedCidRefs = [
      $group1['table_name'] => [
        $customField[0]['column_name'],
        $customField[1]['column_name'],
      ],
      $group2['table_name'] => [
        'entity_id',
        $customField[2]['column_name'],
        $customField[3]['column_name'],
      ],
    ];
    $cidRefs = CRM_Core_DAO::getReferencesToContactTable();
    foreach ($expectedCidRefs as $table => $refs) {
      $this->assertEquals($refs, $cidRefs[$table]);
    }
  }

  /**
   * Test our ability to alter the maximum execution time temporarily.
   *
   * https://mariadb.com/kb/en/aborting-statements/
   *
   * @return void
   */
  public function testSetMaxExecutionTime() {
    $original = CRM_Core_DAO::getMaxExecutionTime();
    $autoClean = CRM_Utils_AutoClean::swapMaxExecutionTime(800);
    $this->assertEquals(800, CRM_Core_DAO::getMaxExecutionTime());
    $autoClean->cleanup();
    $this->assertEquals($original, CRM_Core_DAO::getMaxExecutionTime());
  }

}

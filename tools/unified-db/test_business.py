import importlib.util
import pathlib
import unittest

HERE = pathlib.Path(__file__).parent
def module(name,filename):
    spec = importlib.util.spec_from_file_location(name,HERE/filename)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result

business = module('business','stage-business.py')
customers = module('customers','customer-candidates.py')

class BusinessMigrationSafetyTests(unittest.TestCase):
    def test_empty_and_missing_relationships_are_distinct(self):
        for value in (None,0,'','0'):
            self.assertEqual(business.relationship(value,{}),('EMPTY',[]))
        self.assertEqual(business.relationship(12,{}),('MISSING',[]))

    def test_nonunique_account_number_is_not_arbitrarily_linked(self):
        self.assertEqual(business.relationship('DD1',{'DD1':['4','7']}),('AMBIGUOUS',['4','7']))
        self.assertEqual(business.relationship('DD1',{'DD1':['4']}),('MATCHED',['4']))

    def test_identification_preserves_leading_zeroes_and_punctuation(self):
        self.assertEqual(customers.identity_number(' 001234 '),'001234')
        self.assertNotEqual(customers.identity_number('001234'),customers.identity_number('1234'))
        self.assertNotEqual(customers.identity_number('AB-12'),customers.identity_number('AB12'))
        self.assertIsNone(customers.identity_number('N/A'))

    def test_invalid_birthdates_do_not_support_matching(self):
        for value in (None,'0000-00-00','1990-02-30',''):
            self.assertIsNone(customers.valid_birthdate(value))
        self.assertIsNotNone(customers.valid_birthdate('1990-02-28'))

if __name__ == '__main__':
    unittest.main()

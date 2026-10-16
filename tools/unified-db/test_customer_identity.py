import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('identity',Path(__file__).with_name('resolve-customer-identities.py'))
identity = importlib.util.module_from_spec(spec)
spec.loader.exec_module(identity)

class StrictIdentityTests(unittest.TestCase):
    def profile(self, **changes):
        row = {'family_name':'Example','first_name':'Buyer','date_of_birth':'1980-01-01','gender':'M'}
        row.update(changes)
        return row

    def test_missing_or_conflicting_ids_never_match(self):
        self.assertEqual(identity.strict_keys(self.profile(),set()),set())
        self.assertEqual(identity.strict_keys(self.profile(),{'001','002'}),set())

    def test_invalid_dates_and_unknown_gender_never_match(self):
        self.assertFalse(identity.strict_keys(self.profile(date_of_birth='0000-00-00'),{'001'}))
        self.assertFalse(identity.strict_keys(self.profile(gender=None),{'001'}))

    def test_each_required_field_changes_identity(self):
        baseline = identity.strict_keys(self.profile(),{'001'})
        for changes in ({'first_name':'Other'},{'date_of_birth':'1980-01-02'},{'gender':'F'}):
            self.assertTrue(baseline.isdisjoint(identity.strict_keys(self.profile(**changes),{'001'})))
        self.assertTrue(baseline.isdisjoint(identity.strict_keys(self.profile(),{'1'})))

    def test_all_name_components_are_required_for_full_name(self):
        self.assertTrue(identity.strict_keys(self.profile(third_name='Extra'),{'001'}).isdisjoint(identity.strict_keys(self.profile(),{'001'})))

if __name__ == '__main__':
    unittest.main()

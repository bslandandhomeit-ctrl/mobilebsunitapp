import importlib.util
import pathlib
import unittest

spec = importlib.util.spec_from_file_location('staging', pathlib.Path(__file__).with_name('stage-properties.py'))
staging = importlib.util.module_from_spec(spec)
spec.loader.exec_module(staging)

class MigrationCandidateTests(unittest.TestCase):
    def test_missing_key_never_matches(self):
        self.assertIsNone(staging.candidate_key(['BCC',None,'L1-12']))
        self.assertIsNone(staging.candidate_key(['BCC','  ','L1-12']))

    def test_hierarchy_and_separators_are_preserved(self):
        self.assertEqual(staging.candidate_key([' BCC ','l1','X']),staging.candidate_key(['bcc','L1','x']))
        self.assertNotEqual(staging.candidate_key(['P1','L1','X']),staging.candidate_key(['P2','L1','X']))
        self.assertNotEqual(staging.candidate_key(['a|b','c']),staging.candidate_key(['a','b|c']))

    def test_duplicate_in_one_source_is_always_ambiguous(self):
        members = [{'source':s,'incomplete':False} for s in ['bsunits','beloan','beloan']]
        self.assertEqual(staging.classify(members),'AMBIGUOUS')
        self.assertEqual(staging.classify(members[:2]),'UNAMBIGUOUS')
        self.assertEqual(staging.classify(members[:1]),'SOURCE_ONLY')

    def test_decimal_formatting_is_not_a_price_conflict_but_null_is(self):
        members = [{'source':'bsunits','id':'1','payload':{'price':90000}},
                   {'source':'beloan','id':'1','payload':{'price':'90000.00'}}]
        self.assertNotIn('price',staging.conflicts(members,'unit'))
        members[1]['payload']['price'] = None
        self.assertIn('price',staging.conflicts(members,'unit'))

    def test_source_payload_is_hex_encoded_not_interpolated(self):
        payload = "'); DROP TABLE units; -- លំនៅ"
        encoded = staging.literal(payload)
        self.assertNotIn('DROP TABLE',encoded)
        self.assertIn(payload.encode('utf8').hex(),encoded)

if __name__ == '__main__':
    unittest.main()

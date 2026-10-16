import importlib.util
import pathlib
import unittest

spec = importlib.util.spec_from_file_location('promotion',pathlib.Path(__file__).with_name('promote-properties.py'))
promotion = importlib.util.module_from_spec(spec)
spec.loader.exec_module(promotion)

class PropertyPromotionTests(unittest.TestCase):
    def test_multiple_source_rows_never_get_first_row_mapping(self):
        matches,_,_ = promotion.unique_matches(
            [(('p','t','u'),{'id':1})],[(('p','t','u'),{'id':2}),(('p','t','u'),{'id':3})])
        self.assertEqual(matches,[])

    def test_incomplete_key_never_promotes_a_match(self):
        matches,_,_ = promotion.unique_matches([(('p','','u'),{'id':1})],[(('p','','u'),{'id':2})])
        self.assertEqual(matches,[])

    def test_matching_preserves_project_and_type_boundaries(self):
        a = [(promotion.natural_key('P','T1','U'),{'id':1})]
        b = [(promotion.natural_key('P','T2','U'),{'id':2})]
        self.assertEqual(promotion.unique_matches(a,b)[0],[])
        b = [(promotion.natural_key(' p ','t1','u'),{'id':2})]
        self.assertEqual(len(promotion.unique_matches(a,b)[0]),1)

if __name__ == '__main__':
    unittest.main()

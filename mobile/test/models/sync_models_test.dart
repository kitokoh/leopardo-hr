import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/models/feature.dart';
import 'package:leopardo_rh/models/feature_manifest.dart';
import 'package:leopardo_rh/models/form_schema.dart';
import 'package:leopardo_rh/models/list_schema.dart';
import 'package:leopardo_rh/models/detail_schema.dart';

void main() {
  group('Sync Models Tests', () {
    test('Feature serialization/deserialization', () {
      final feature = Feature(
        key: 'test_feature',
        title: 'Test Feature',
        description: 'A test feature',
        endpoint: '/api/test',
        methods: ['GET', 'POST'],
        parameters: {'test': 'value'},
        responseSchema: {'result': 'string'},
        permissions: ['test.view'],
        minimumMobileVersion: '1.0.0',
        type: FeatureType.generic,
      );

      final json = feature.toJson();
      final deserializedFeature = Feature.fromJson(json);

      expect(deserializedFeature.key, equals(feature.key));
      expect(deserializedFeature.title, equals(feature.title));
      expect(deserializedFeature.type, equals(feature.type));
    });

    test('FeatureManifest serialization/deserialization', () {
      final manifest = FeatureManifest(
        version: '1.0.0',
        generatedAt: DateTime.parse('2024-01-15T10:30:00Z'),
        mobileVersionMin: '1.0.0',
        signature: 'test_signature',
        features: [],
      );

      final json = manifest.toJson();
      final deserializedManifest = FeatureManifest.fromJson(json);

      expect(deserializedManifest.version, equals(manifest.version));
      expect(deserializedManifest.signature, equals(manifest.signature));
    });

    test('FormSchema validation', () {
      final formSchema = FormSchema(
        fields: [
          FormField(
            name: 'email',
            type: FormFieldType.email,
            label: 'Email',
            required: true,
          ),
          FormField(
            name: 'name',
            type: FormFieldType.text,
            label: 'Name',
            required: true,
            validation: {'min_length': 2},
          ),
        ],
      );

      // Test valid data
      final validData = {
        'email': 'test@example.com',
        'name': 'John Doe',
      };
      final validResult = formSchema.validate(validData);
      expect(validResult.isValid, isTrue);

      // Test invalid data
      final invalidData = {
        'email': 'invalid-email',
        'name': 'J', // Too short
      };
      final invalidResult = formSchema.validate(invalidData);
      expect(invalidResult.isValid, isFalse);
      expect(invalidResult.errors, isNotEmpty);
    });

    test('ListColumn formatting', () {
      final textColumn = ListColumn(
        name: 'name',
        label: 'Name',
        type: ListColumnType.text,
      );

      final dateColumn = ListColumn(
        name: 'date',
        label: 'Date',
        type: ListColumnType.date,
      );

      final currencyColumn = ListColumn(
        name: 'price',
        label: 'Price',
        type: ListColumnType.currency,
      );

      expect(textColumn.formatValue('Test'), equals('Test'));
      expect(currencyColumn.formatValue(123.45), equals('123.45 €'));

      final testDate = DateTime(2024, 1, 15);
      expect(dateColumn.formatValue(testDate), equals('15/01/2024'));
    });

    test('Version comparison', () {
      final version1 = Version.parse('1.2.0');
      final version2 = Version.parse('1.1.5');
      final version3 = Version.parse('1.2.0');

      expect(version1 > version2, isTrue);
      expect(version1 < version2, isFalse);
      expect(version1 == version3, isTrue);
      expect(version1 >= version3, isTrue);
    });

    test('Feature compatibility check', () {
      final feature = Feature(
        key: 'test',
        title: 'Test',
        description: 'Test',
        endpoint: '/test',
        methods: ['GET'],
        parameters: {},
        responseSchema: {},
        permissions: [],
        minimumMobileVersion: '1.0.0',
        maximumMobileVersion: '2.0.0',
        type: FeatureType.generic,
      );

      expect(feature.isCompatibleWith('1.5.0'), isTrue);
      expect(feature.isCompatibleWith('0.9.0'), isFalse);
      expect(feature.isCompatibleWith('2.1.0'), isFalse);
    });

    test('Feature permissions check', () {
      final feature = Feature(
        key: 'test',
        title: 'Test',
        description: 'Test',
        endpoint: '/test',
        methods: ['GET'],
        parameters: {},
        responseSchema: {},
        permissions: ['test.view', 'test.edit'],
        minimumMobileVersion: '1.0.0',
        type: FeatureType.generic,
      );

      expect(
          feature.hasRequiredPermissions(['test.view', 'test.edit', 'other']),
          isTrue);
      expect(feature.hasRequiredPermissions(['test.view']), isFalse);
      expect(feature.hasRequiredPermissions([]), isFalse);
    });

    test('ManifestDiff calculation', () {
      final feature1 = Feature(
        key: 'feature1',
        title: 'Feature 1',
        description: 'First feature',
        endpoint: '/api/1',
        methods: ['GET'],
        parameters: {},
        responseSchema: {},
        permissions: [],
        minimumMobileVersion: '1.0.0',
        type: FeatureType.generic,
      );

      final feature2 = Feature(
        key: 'feature2',
        title: 'Feature 2',
        description: 'Second feature',
        endpoint: '/api/2',
        methods: ['GET'],
        parameters: {},
        responseSchema: {},
        permissions: [],
        minimumMobileVersion: '1.0.0',
        type: FeatureType.generic,
      );

      final manifest1 = FeatureManifest(
        version: '1.0.0',
        generatedAt: DateTime.now(),
        mobileVersionMin: '1.0.0',
        signature: 'sig1',
        features: [feature1],
      );

      final manifest2 = FeatureManifest(
        version: '1.1.0',
        generatedAt: DateTime.now(),
        mobileVersionMin: '1.0.0',
        signature: 'sig2',
        features: [feature1, feature2],
      );

      final diff = manifest1.compareWith(manifest2);

      expect(diff.hasChanges, isTrue);
      expect(diff.newFeatures.length, equals(1));
      expect(diff.newFeatures.first.key, equals('feature2'));
      expect(diff.removedFeatures.length, equals(0));
    });
  });
}

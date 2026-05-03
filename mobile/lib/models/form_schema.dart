import 'package:json_annotation/json_annotation.dart';

part 'form_schema.g.dart';

@JsonSerializable()
class FormSchema {
  final List<FormField> fields;
  final Map<String, dynamic>? validation;
  final String? submitEndpoint;
  final String? submitMethod;
  final Map<String, String>? labels;

  const FormSchema({
    required this.fields,
    this.validation,
    this.submitEndpoint,
    this.submitMethod,
    this.labels,
  });

  factory FormSchema.fromJson(Map<String, dynamic> json) => 
      _$FormSchemaFromJson(json);

  Map<String, dynamic> toJson() => _$FormSchemaToJson(this);

  /// Obtient un champ par son nom
  FormField? getField(String name) {
    return fields.where((field) => field.name == name).firstOrNull;
  }

  /// Obtient tous les champs requis
  List<FormField> get requiredFields {
    return fields.where((field) => field.required).toList();
  }

  /// Valide les données du formulaire selon le schéma
  FormValidationResult validate(Map<String, dynamic> data) {
    final errors = <String, String>{};

    for (final field in fields) {
      final value = data[field.name];
      final fieldError = field.validate(value);
      
      if (fieldError != null) {
        errors[field.name] = fieldError;
      }
    }

    return FormValidationResult(
      isValid: errors.isEmpty,
      errors: errors,
    );
  }

  @override
  String toString() => 'FormSchema(fields: ${fields.length})';
}

@JsonSerializable()
class FormField {
  final String name;
  final FormFieldType type;
  final String label;
  final bool required;
  final Map<String, dynamic>? validation;
  final dynamic defaultValue;
  final List<FormFieldOption>? options;
  final String? placeholder;
  final String? helpText;

  const FormField({
    required this.name,
    required this.type,
    required this.label,
    this.required = false,
    this.validation,
    this.defaultValue,
    this.options,
    this.placeholder,
    this.helpText,
  });

  factory FormField.fromJson(Map<String, dynamic> json) => 
      _$FormFieldFromJson(json);

  Map<String, dynamic> toJson() => _$FormFieldToJson(this);

  /// Valide une valeur selon les règles du champ
  String? validate(dynamic value) {
    // Vérification de champ requis
    if (required && (value == null || value.toString().trim().isEmpty)) {
      return 'Ce champ est requis';
    }

    // Si pas de valeur et pas requis, pas d'erreur
    if (value == null || value.toString().trim().isEmpty) {
      return null;
    }

    // Validation selon le type
    switch (type) {
      case FormFieldType.email:
        if (!_isValidEmail(value.toString())) {
          return 'Adresse email invalide';
        }
        break;
      case FormFieldType.number:
        if (double.tryParse(value.toString()) == null) {
          return 'Valeur numérique invalide';
        }
        break;
      case FormFieldType.phone:
        if (!_isValidPhone(value.toString())) {
          return 'Numéro de téléphone invalide';
        }
        break;
      case FormFieldType.url:
        if (!_isValidUrl(value.toString())) {
          return 'URL invalide';
        }
        break;
      default:
        break;
    }

    // Validation personnalisée
    if (validation != null) {
      final minLength = validation!['min_length'] as int?;
      final maxLength = validation!['max_length'] as int?;
      final pattern = validation!['pattern'] as String?;

      if (minLength != null && value.toString().length < minLength) {
        return 'Minimum $minLength caractères requis';
      }

      if (maxLength != null && value.toString().length > maxLength) {
        return 'Maximum $maxLength caractères autorisés';
      }

      if (pattern != null && !RegExp(pattern).hasMatch(value.toString())) {
        return 'Format invalide';
      }
    }

    return null;
  }

  bool _isValidEmail(String email) {
    return RegExp(r'^[^@]+@[^@]+\.[^@]+$').hasMatch(email);
  }

  bool _isValidPhone(String phone) {
    return RegExp(r'^\+?[\d\s\-\(\)]+$').hasMatch(phone);
  }

  bool _isValidUrl(String url) {
    return Uri.tryParse(url) != null;
  }

  @override
  String toString() => 'FormField(name: $name, type: $type, required: $required)';
}

@JsonEnum()
enum FormFieldType {
  @JsonValue('text')
  text,
  @JsonValue('email')
  email,
  @JsonValue('password')
  password,
  @JsonValue('number')
  number,
  @JsonValue('phone')
  phone,
  @JsonValue('url')
  url,
  @JsonValue('textarea')
  textarea,
  @JsonValue('select')
  select,
  @JsonValue('multiselect')
  multiselect,
  @JsonValue('checkbox')
  checkbox,
  @JsonValue('radio')
  radio,
  @JsonValue('date')
  date,
  @JsonValue('datetime')
  datetime,
  @JsonValue('time')
  time,
  @JsonValue('file')
  file,
  @JsonValue('image')
  image,
  @JsonValue('hidden')
  hidden;
}

@JsonSerializable()
class FormFieldOption {
  final String value;
  final String label;
  final bool disabled;

  const FormFieldOption({
    required this.value,
    required this.label,
    this.disabled = false,
  });

  factory FormFieldOption.fromJson(Map<String, dynamic> json) => 
      _$FormFieldOptionFromJson(json);

  Map<String, dynamic> toJson() => _$FormFieldOptionToJson(this);

  @override
  String toString() => 'FormFieldOption(value: $value, label: $label)';
}

@JsonSerializable()
class FormValidationResult {
  final bool isValid;
  final Map<String, String> errors;

  const FormValidationResult({
    required this.isValid,
    required this.errors,
  });

  factory FormValidationResult.fromJson(Map<String, dynamic> json) => 
      _$FormValidationResultFromJson(json);

  Map<String, dynamic> toJson() => _$FormValidationResultToJson(this);

  /// Obtient l'erreur pour un champ spécifique
  String? getError(String fieldName) => errors[fieldName];

  /// Vérifie si un champ a une erreur
  bool hasError(String fieldName) => errors.containsKey(fieldName);

  @override
  String toString() => 'FormValidationResult(isValid: $isValid, errors: ${errors.length})';
}

/// Extension pour ajouter firstOrNull si pas disponible
extension FormFieldListExtension on List<FormField> {
  FormField? get firstOrNull => isEmpty ? null : first;
}
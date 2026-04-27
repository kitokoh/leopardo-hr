import yaml
import json
import os
import re

def resolve_ref(ref, spec):
    parts = ref.split('/')
    current = spec
    for part in parts[1:]:
        current = current.get(part)
    return current

def generate_example(schema, spec, seen_refs=None):
    if seen_refs is None:
        seen_refs = set()

    if '$ref' in schema:
        ref = schema['$ref']
        if ref in seen_refs:
            return None # Avoid infinite recursion
        resolved = resolve_ref(ref, spec)
        return generate_example(resolved, spec, seen_refs | {ref})

    if 'example' in schema:
        return schema['example']

    if 'oneOf' in schema:
        return generate_example(schema['oneOf'][0], spec, seen_refs)

    if 'allOf' in schema:
        result = {}
        for subschema in schema['allOf']:
            sub_example = generate_example(subschema, spec, seen_refs)
            if isinstance(sub_example, dict):
                result.update(sub_example)
        return result

    schema_type = schema.get('type')

    if schema_type == 'object':
        result = {}
        properties = schema.get('properties', {})
        for prop_name, prop_schema in properties.items():
            result[prop_name] = generate_example(prop_schema, spec, seen_refs)
        return result

    if schema_type == 'array':
        items_schema = schema.get('items', {})
        return [generate_example(items_schema, spec, seen_refs)]

    if schema_type == 'string':
        fmt = schema.get('format')
        if fmt == 'date-time':
            return '2026-05-22T10:00:00Z'
        if fmt == 'date':
            return '2026-05-22'
        if fmt == 'email':
            return 'example@leopardo-rh.com'
        if fmt == 'uuid':
            return '550e8400-e29b-41d4-a716-446655440000'
        return 'string'

    if schema_type == 'integer':
        return 1

    if schema_type == 'number':
        return 1.5

    if schema_type == 'boolean':
        return True

    return None

def main():
    openapi_path = 'api/openapi.yaml'
    output_dir = 'docs/api-mock-data'

    os.makedirs(output_dir, exist_ok=True)

    with open(openapi_path, 'r') as f:
        spec = yaml.safe_load(f)

    paths = spec.get('paths', {})
    for path, methods in paths.items():
        for method, details in methods.items():
            responses = details.get('responses', {})
            for status, response_details in responses.items():
                if status == '200' or status == '201':
                    content = response_details.get('content', {})
                    if 'application/json' in content:
                        schema = content['application/json'].get('schema')
                        example = generate_example(schema, spec)

                        # Create a filename based on the path and method
                        filename = path.strip('/').replace('/', '_').replace('{', '').replace('}', '')
                        if filename == '':
                            filename = 'root'

                        file_path = os.path.join(output_dir, f"{method}_{filename}_{status}.json")
                        with open(file_path, 'w') as out_f:
                            json.dump(example, out_f, indent=2)
                        print(f"Generated {file_path}")

if __name__ == '__main__':
    main()

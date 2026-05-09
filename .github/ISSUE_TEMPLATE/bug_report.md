name: 🐛 Bug Report
description: Report a bug to help us improve Leopardo RH
labels: ["bug", "triage"]
body:
  - type: markdown
    attributes:
      value: |
        Thanks for taking the time to fill out this bug report!
  - type: textarea
    id: description
    attributes:
      label: Description
      description: A clear and concise description of what the bug is.
    validations:
      required: true
  - type: textarea
    id: reproduction
    attributes:
      label: Reproduction Steps
      description: How can we reproduce this issue?
      placeholder: |
        1. Go to '...'
        2. Click on '....'
        3. Scroll down to '....'
        4. See error
    validations:
      required: true
  - type: textarea
    id: expected
    attributes:
      label: Expected Behavior
      description: A clear and concise description of what you expected to happen.
    validations:
      required: true
  - type: textarea
    id: context
    attributes:
      label: Context
      description: |
        - Environment: (Local Docker, Render, Staging)
        - Device/Browser: (iPhone 13, Chrome 124, ZKTeco K40)
        - Version: (Found in /api/v1/health)
  - type: textarea
    id: logs
    attributes:
      label: Logs or Screenshots
      description: Please paste any relevant logs or drag and drop screenshots here.

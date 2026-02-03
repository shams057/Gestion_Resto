import torch
print(torch.version.cuda)            # e.g., 12.1
print(torch.cuda.is_available())     # should be True
print(torch.cuda.get_device_name(0)) # RTX 4060
